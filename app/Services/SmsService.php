<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send OTP via SMS provider.
     *
     * Supports MSG91 (configured via config('services.msg91'))
     *
     * @return bool true if sent successfully
     */
    public static function sendOtp(string $phone, string $otp): bool
    {
        $provider = config('sms.provider', 'msg91');

        return match ($provider) {
            'msg91' => self::sendViaMsg91($phone, $otp),
            'log' => self::sendViaLog($phone, $otp),
            default => self::sendViaLog($phone, $otp),
        };
    }

    /**
     * Send OTP via MSG91 API.
     *
     * Config needed in .env:
     *   SMS_PROVIDER=msg91
     *   MSG91_AUTH_KEY=your_auth_key
     *   MSG91_TEMPLATE_ID=your_template_id
     *   MSG91_SENDER_ID=your_sender_id
     */
    private static function sendViaMsg91(string $phone, string $otp): bool
    {
        $authKey = config('services.msg91.auth_key');
        $templateId = config('services.msg91.template_id');
        $senderId = config('services.msg91.sender_id', 'EIHONE');

        if (! $authKey || ! $templateId) {
            Log::warning('MSG91 not configured. Falling back to log.', [
                'phone' => $phone,
                'otp' => $otp,
            ]);

            return self::sendViaLog($phone, $otp);
        }

        // Clean phone number - remove spaces, dashes, country code prefix
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10); // Last 10 digits for Indian numbers
        }

        try {
            $response = Http::timeout(10)->post('https://api.msg91.com/api/v5/otp', [
                'mobile' => $cleanPhone,
                'otp' => $otp,
                'authkey' => $authKey,
                'sender' => $senderId,
                'otp_length' => 6,
                'otp_expiry' => 10,
            ]);

            if ($response->successful()) {
                Log::info('OTP sent via MSG91', ['phone' => $cleanPhone]);

                return true;
            }

            Log::error('MSG91 API error', [
                'phone' => $cleanPhone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('MSG91 send failed', [
                'phone' => $cleanPhone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Log OTP (for development/testing).
     */
    private static function sendViaLog(string $phone, string $otp): bool
    {
        Log::info('OTP (dev mode)', [
            'phone' => $phone,
            'otp' => $otp,
        ]);

        return true;
    }
}
