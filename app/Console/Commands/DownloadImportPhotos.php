<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadImportPhotos extends Command
{
    protected $signature = 'photos:download {--limit=10} {--business-id=}';
    protected $description = 'Download external photos for imported businesses to cloud or local storage';

    private function getDisk(): string
    {
        return Setting::get('bunny_zone_name') && Setting::get('bunny_access_key') ? 'bunny' : 'public';
    }

    private function getPhotoPath(string $slug, string $ext): string
    {
        return 'businesses/' . $slug . '_' . Str::random(6) . '.' . $ext;
    }

    public function handle()
    {
        $disk = $this->getDisk();
        $this->info("Using storage disk: {$disk}");

        $query = Business::where('source', 'import')
            ->whereNull('photos_downloaded_at')
            ->whereNotNull('photos');

        if ($this->option('business-id')) {
            $query->where('id', $this->option('business-id'));
        }

        $businesses = $query->limit($this->option('limit'))->get();

        if ($businesses->isEmpty()) {
            $this->info('No businesses with pending photo downloads.');
            return 0;
        }

        $downloaded = 0;
        foreach ($businesses as $business) {
            $photos = $business->photos;
            if (!is_array($photos) || empty($photos)) {
                $business->update(['photos_downloaded_at' => now()]);
                continue;
            }

            $savedPhotos = [];
            foreach ($photos as $photoUrl) {
                // Already saved locally or on bunny
                if (str_starts_with($photoUrl, 'storage/') || str_starts_with($photoUrl, 'http')) {
                    // If it's an external URL, download it
                    if (str_starts_with($photoUrl, 'http')) {
                        try {
                            $response = Http::timeout(10)->get($photoUrl);
                            if ($response->successful() && strlen($response->body()) > 100) {
                                $ext = match (true) {
                                    str_contains($response->header('Content-Type', ''), 'png') => 'png',
                                    str_contains($response->header('Content-Type', ''), 'webp') => 'webp',
                                    str_contains($response->header('Content-Type', ''), 'gif') => 'gif',
                                    default => 'jpg',
                                };
                                $path = $this->getPhotoPath($business->slug, $ext);
                                Storage::disk($disk)->put($path, $response->body());
                                $savedPhotos[] = $disk === 'bunny'
                                    ? (Setting::get('bunny_pull_zone_url') ?: Setting::get('bunny_cdn_url')) . '/' . $path
                                    : 'storage/' . $path;
                            }
                        } catch (\Exception $e) {
                            $this->warn("  Failed: {$photoUrl}");
                        }
                    } else {
                        $savedPhotos[] = $photoUrl;
                    }
                }
            }

            if (!empty($savedPhotos)) {
                $business->update([
                    'photos' => $savedPhotos,
                    'photos_downloaded_at' => now(),
                ]);
                $downloaded++;
                $this->info("  OK: {$business->name} (" . count($savedPhotos) . " photos)");
            } else {
                $business->update(['photos_downloaded_at' => now()]);
            }
        }

        $this->info("Downloaded photos for {$downloaded} businesses.");
        return 0;
    }
}
