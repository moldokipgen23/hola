<?php

namespace App\Http\Controllers\Integration;

use App\Models\IntegrationWebhook;
use App\Models\IntegrationWebhookDelivery;
use Illuminate\Http\Request;

class WebhookSubscriptionController extends BaseController
{
    public function index(Request $request)
    {
        $webhooks = IntegrationWebhook::when($request->tenant_type, fn ($q, $v) => $q->where('tenant_type', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->ok($webhooks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_type' => 'required|string|max:50',
            'tenant_id' => 'required|integer',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
        ]);

        $webhook = IntegrationWebhook::create($validated);

        return $this->created($webhook);
    }

    public function show($id)
    {
        return $this->ok(IntegrationWebhook::withCount('deliveries')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $webhook = IntegrationWebhook::findOrFail($id);

        $validated = $request->validate([
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string',
            'is_active' => 'sometimes|boolean',
        ]);

        $webhook->update($validated);

        return $this->ok($webhook->fresh());
    }

    public function destroy($id)
    {
        $webhook = IntegrationWebhook::findOrFail($id);
        $webhook->delete();

        return $this->noContent();
    }

    public function deliveries(Request $request, $id)
    {
        $deliveries = IntegrationWebhookDelivery::where('webhook_id', $id)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return $this->ok($deliveries);
    }

    public function retry($id)
    {
        $delivery = IntegrationWebhookDelivery::findOrFail($id);

        if ($delivery->status === 'delivered') {
            return $this->error('This delivery was already successful.', 400);
        }

        $webhook = $delivery->webhook;
        if (!$webhook || !$webhook->is_active) {
            return $this->error('Webhook endpoint is inactive.', 400);
        }

        $payload = is_array($delivery->payload) ? $delivery->payload : (json_decode($delivery->payload, true) ?? []);
        $service = app(\App\Services\IntegrationWebhookService::class);
        $result = $service->send($webhook, $delivery->event, $payload);

        return $this->ok($result);
    }
}
