<?php

namespace App\Services;

use App\Models\Business;
use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unified messaging for notifying businesses (claim invitations, general
 * announcements). Supports WhatsApp (CallMeBot), SMS (MSG91) and Telegram.
 * Always logs every attempt to NotificationLog.
 */
class ClaimNotificationService
{
    /**
     * Render a template with business placeholders.
     * Available: {business_name}, {claim_url}, {site_name}, {district}, {support_phone}
     */
    public function renderTemplate(string $template, Business $business): string
    {
        $replacements = [
            '{business_name}' => $business->name,
            '{claim_url}' => route('public.claim', $business->id),
            '{site_name}' => Setting::get('site_name', 'Eiho One'),
            '{district}' => $business->district ?? 'your area',
            '{support_phone}' => Setting::get('contact_phone', ''),
        ];

        return strtr($template, $replacements);
    }

    /**
     * Normalize an Indian phone to E.164 (10-digit local -> 91XXXXXXXXXX).
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91'.substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    /**
     * The channels that are currently configured (have a key set).
     */
    public function configuredChannels(): array
    {
        $channels = [];

        if (Setting::get('whatsapp_meta_token') && Setting::get('whatsapp_meta_phone_id')) {
            $channels['whatsapp_meta'] = 'WhatsApp (Meta Cloud API)';
        } elseif (Setting::get('callmebot_api_key')) {
            $channels['whatsapp'] = 'WhatsApp (CallMeBot)';
        }
        if (Setting::get('sms_msg91_auth_key') || config('services.msg91.auth_key')) {
            $channels['sms'] = 'SMS (MSG91)';
        }
        if (Setting::get('telegram_bot_token') && Setting::get('telegram_chat_id')) {
            $channels['telegram'] = 'Telegram';
        }

        return $channels;
    }

    /**
     * Send a message to a business via a specific channel. Returns [sent, channel].
     */
    public function send(Business $business, string $message, string $preferred = 'whatsapp_meta'): array
    {
        $order = ['whatsapp_meta', 'whatsapp', 'sms', 'telegram'];
        if (in_array($preferred, $order, true)) {
            $order = array_merge([$preferred], array_diff($order, [$preferred]));
        }

        $usedChannel = null;
        $sent = false;

        foreach ($order as $channel) {
            if ($sent) {
                break;
            }
            $sent = match ($channel) {
                'whatsapp_meta' => $this->sendWhatsAppMeta($business->phone, $message),
                'whatsapp' => $this->sendWhatsApp($business->phone, $message),
                'sms' => $this->sendSms($business->phone, $message),
                'telegram' => $this->sendTelegram($message),
                default => false,
            };
            if ($sent) {
                $usedChannel = $channel;
            }
        }

        // Log every attempt.
        try {
            NotificationLog::create([
                'business_id' => $business->id,
                'type' => 'claim_invitation',
                'channel' => $usedChannel ?? 'none',
                'recipient' => $business->phone ?? $business->email ?? 'unknown',
                'message' => $message,
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => $sent ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log notification', ['business' => $business->id, 'error' => $e->getMessage()]);
        }

        return ['sent' => $sent, 'channel' => $usedChannel];
    }

    /**
     * Send via the official Meta WhatsApp Business Cloud API.
     * Requires: access token, phone-number-id (from Meta Developer console),
     * and the business phone must be a verified Meta Business API number.
     * Message templates must be approved by Meta for business-initiated texts.
     */
    private function sendWhatsAppMeta(?string $phone, string $message): bool
    {
        $token = Setting::get('whatsapp_meta_token') ?? config('services.whatsapp_meta.token');
        $phoneNumberId = Setting::get('whatsapp_meta_phone_id') ?? config('services.whatsapp_meta.phone_number_id');
        $templateName = Setting::get('whatsapp_meta_template', 'claim_invitation');
        $language = Setting::get('whatsapp_meta_language', 'en');

        $to = $this->normalizePhone($phone);
        if (! $token || ! $phoneNumberId || ! $to) {
            return false;
        }

        try {
            $response = Http::withToken($token)->timeout(15)
                ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $language],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [['type' => 'text', 'text' => $message]],
                            ],
                        ],
                    ],
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Meta send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendWhatsApp(?string $phone, string $message): bool
    {
        $apiKey = Setting::get('callmebot_api_key') ?? config('services.callmebot.api_key');
        $to = $this->normalizePhone($phone);
        if (! $apiKey || ! $to) {
            return false;
        }

        try {
            $response = Http::timeout(15)->get('https://api.callmebot.com/whatsapp.php', [
                'phone' => $to,
                'text' => $message,
                'apikey' => $apiKey,
            ]);

            return $response->successful() && ! str_contains($response->body(), 'ERROR');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendSms(?string $phone, string $message): bool
    {
        $authKey = Setting::get('sms_msg91_auth_key') ?? config('services.msg91.auth_key');
        $templateId = Setting::get('sms_msg91_template_id') ?? config('services.msg91.template_id');
        $to = $this->normalizePhone($phone);
        if (! $authKey || ! $templateId || ! $to) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'authkey' => $authKey,
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/flow/', [
                'template_id' => $templateId,
                'sender' => config('services.msg91.sender_id', 'EIHONE'),
                'short_url' => 0,
                'mobiles' => $to,
                'VAR1' => $message,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('SMS send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendTelegram(string $message): bool
    {
        $token = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');
        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            return $response->successful() && ($response->json('ok') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
