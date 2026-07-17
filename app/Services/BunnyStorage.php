<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class BunnyStorage
{
    public static function isConfigured(): bool
    {
        return (bool) (Setting::get('bunny_zone_name') && Setting::get('bunny_access_key'));
    }

    public static function getZoneName(): ?string
    {
        return Setting::get('bunny_zone_name');
    }

    public static function getCdnUrl(): string
    {
        return Setting::get('bunny_pull_zone_url')
            ?: Setting::get('bunny_cdn_url')
            ?: 'https://'.Setting::get('bunny_zone_name').'.b-cdn.net';
    }

    public static function getStorageUrl(): string
    {
        $zoneName = Setting::get('bunny_zone_name');
        $region = Setting::get('bunny_region', 'sg');

        return "https://{$region}-s3.storage.bunnycdn.com";
    }

    public static function put(string $path, string $contents): bool
    {
        if (! self::isConfigured()) {
            return false;
        }

        $url = self::getStorageUrl().'/'.$path;
        $key = Setting::get('bunny_access_key');

        $response = Http::withHeaders([
            'AccessKey' => $key,
            'Content-Type' => self::guessMimeType($path),
        ])->withBody($contents, 'application/octet-stream')
            ->put($url);

        return $response->successful();
    }

    public static function getPublicUrl(string $path): string
    {
        return self::getCdnUrl().'/'.$path;
    }

    private static function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
    }
}
