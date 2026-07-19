<?php

namespace App\Services;

use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationWebhookService
{
    public function dispatch(string $event, array $payload, ?string $tenantType = null, ?int $tenantId = null): void
    {
        $query = IntegrationWebhook::active()->forEvent($event);

        if ($tenantType && $tenantId) {
            $query->forTenant($tenantType, $tenantId);
        }

        $webhooks = $query->cursor();

        foreach ($webhooks as $webhook) {
            $this->send($webhook, $event, $payload);
        }
    }

    public function dispatchForTenant(string $tenantType, int $tenantId, string $event, array $payload): void
    {
        $this->dispatch($event, $payload, $tenantType, $tenantId);
    }

    public function send(IntegrationWebhook $webhook, string $event, array $payload): IntegrationWebhookDelivery
    {
        $delivery = IntegrationWebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        try {
            $signature = $this->signPayload($payload, $webhook->secret);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Ehlom-Webhook-Id' => (string) $delivery->id,
                    'X-Ehlom-Webhook-Event' => $event,
                    'X-Ehlom-Webhook-Signature' => $signature,
                    'X-Ehlom-Webhook-Timestamp' => (string) now()->timestamp,
                    'User-Agent' => 'Ehlom-Webhook/1.0',
                ])
                ->post($webhook->url, [
                    'event' => $event,
                    'payload' => $payload,
                    'sent_at' => now()->toIso8601String(),
                ]);

            $delivery->update([
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 5000),
                'status' => $response->successful() ? 'delivered' : 'failed',
                'attempts' => $delivery->attempts + 1,
                'delivered_at' => $response->successful() ? now() : null,
                'next_attempt_at' => $response->successful() ? null : $this->nextRetry($delivery->attempts + 1),
            ]);

            if ($response->successful()) {
                $webhook->update(['last_success_at' => now()]);
            } else {
                $webhook->update(['last_failure_at' => now()]);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'response_code' => 0,
                'response_body' => $e->getMessage(),
                'status' => 'failed',
                'attempts' => $delivery->attempts + 1,
                'next_attempt_at' => $this->nextRetry($delivery->attempts + 1),
            ]);
            $webhook->update(['last_failure_at' => now()]);

            Log::warning('Webhook delivery failed', [
                'delivery_id' => $delivery->id,
                'webhook_id' => $webhook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        return $delivery->fresh();
    }

    public function retryFailed(): void
    {
        $deliveries = IntegrationWebhookDelivery::where('status', 'failed')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where('attempts', '<', 10)
            ->cursor();

        foreach ($deliveries as $delivery) {
            $webhook = $delivery->webhook;
            if ($webhook && $webhook->is_active) {
                $payload = is_array($delivery->payload) ? $delivery->payload : json_decode($delivery->payload, true) ?? [];
                $this->send($webhook, $delivery->event, $payload);
            }
        }
    }

    private function signPayload(array $payload, string $secret): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        return hash_hmac('sha256', $json, $secret);
    }

    private function nextRetry(int $attempts): ?\DateTimeInterface
    {
        $delays = [1, 5, 15, 30, 60, 120, 300, 600, 1800, 3600];

        $minutes = $delays[min($attempts - 1, count($delays) - 1)];

        return now()->addMinutes($minutes);
    }
}
