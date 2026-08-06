<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PushToken;
use App\Models\VendorNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private string $fcmUrl = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';

    private ?string $fcmServerKey;

    private ?string $fcmProjectId;

    public function __construct()
    {
        $this->fcmServerKey = config('services.firebase.server_key');
        $this->fcmProjectId = config('services.firebase.project_id');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->fcmServerKey) && ! empty($this->fcmProjectId);
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $tokens = PushToken::active()->forUser($userId)->get();

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'message' => 'No active tokens'];
        }

        $sent = 0;
        $failedTokens = [];

        foreach ($tokens as $pushToken) {
            $result = $this->sendFcmMessage($pushToken->token, $title, $body, $data);
            if ($result['success']) {
                $sent++;
                $pushToken->touchLastUsed();
            } else {
                $failedTokens[] = $pushToken->token;
                if ($result['invalid_token']) {
                    $pushToken->deactivate();
                }
            }
        }

        return ['sent' => $sent, 'total' => $tokens->count(), 'failed' => count($failedTokens)];
    }

    public function sendToBusiness(int $businessId, string $title, string $body, array $data = []): array
    {
        $tokens = PushToken::active()->forBusiness($businessId)->get();

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'message' => 'No active tokens'];
        }

        $sent = 0;
        $failedTokens = [];

        foreach ($tokens as $pushToken) {
            $result = $this->sendFcmMessage($pushToken->token, $title, $body, $data);
            if ($result['success']) {
                $sent++;
                $pushToken->touchLastUsed();
            } else {
                $failedTokens[] = $pushToken->token;
                if ($result['invalid_token']) {
                    $pushToken->deactivate();
                }
            }
        }

        return ['sent' => $sent, 'total' => $tokens->count(), 'failed' => count($failedTokens)];
    }

    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        return $this->sendFcmMessage($token, $title, $body, $data);
    }

    public function broadcast(string $title, string $body, array $data = []): array
    {
        $tokens = PushToken::active()->get();
        $sent = 0;

        foreach ($tokens->chunk(500) as $chunk) {
            $registrationIds = $chunk->pluck('token')->toArray();
            $result = $this->sendFcmBulk($registrationIds, $title, $body, $data);
            $sent += $result['sent'] ?? 0;
        }

        return ['sent' => $sent, 'total' => $tokens->count()];
    }

    public function sendAndStore(int $userId, string $type, string $title, string $body, array $data = [], ?int $businessId = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $this->sendToUser($userId, $title, $body, array_merge($data, ['type' => $type]));
    }

    public function sendVendorNotification(int $businessId, string $type, string $title, ?string $body = null, array $data = []): void
    {
        VendorNotification::create([
            'business_id' => $businessId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if ($this->isConfigured()) {
            $this->sendToBusiness($businessId, $title, $body ?? '', array_merge($data, ['type' => $type]));
        }
    }

    private function sendFcmMessage(string $token, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            Log::info('FCM not configured, skipping push', ['token' => substr($token, 0, 20).'...']);

            return ['success' => false, 'invalid_token' => false, 'message' => 'FCM not configured'];
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->fcmProjectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'eiho_default',
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'badge' => 1,
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return ['success' => true, 'invalid_token' => false];
            }

            $body = $response->json();
            if (isset($body['error']['code']) && $body['error']['code'] === 404) {
                return ['success' => false, 'invalid_token' => true];
            }

            Log::warning('FCM send failed', ['status' => $response->status(), 'body' => $body]);

            return ['success' => false, 'invalid_token' => false];

        } catch (\Exception $e) {
            Log::error('FCM send error', ['message' => $e->getMessage()]);

            return ['success' => false, 'invalid_token' => false];
        }
    }

    private function sendFcmBulk(array $tokens, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured() || empty($tokens)) {
            return ['sent' => 0];
        }

        try {
            $accessToken = $this->getAccessToken();
            $sent = 0;

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->fcmProjectId}/messages:send", [
                    'message' => [
                        'topic' => 'broadcast',
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_map('strval', $data),
                    ],
                ]);

            if ($response->successful()) {
                $sent = count($tokens);
            }

            return ['sent' => $sent];
        } catch (\Exception $e) {
            Log::error('FCM bulk send error', ['message' => $e->getMessage()]);

            return ['sent' => 0];
        }
    }

    private function getAccessToken(): string
    {
        $serviceAccountPath = config('services.firebase.service_account_path');

        if (! $serviceAccountPath || ! file_exists($serviceAccountPath)) {
            throw new \RuntimeException('Firebase service account not found');
        }

        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

        $now = time();
        $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtPayload = base64_encode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signatureInput = "$jwtHeader.$jwtPayload";
        openssl_sign($signatureInput, $signature, $serviceAccount['private_key'], 'SHA256');
        $jwt = "$signatureInput.".base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json('access_token');
    }
}
