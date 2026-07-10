<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadImportPhotos extends Command
{
    protected $signature = 'photos:download {--limit=10} {--business-id=}';
    protected $description = 'Download external photos for imported businesses to local storage';

    public function handle()
    {
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

            $localPhotos = [];
            foreach ($photos as $photoUrl) {
                if (str_starts_with($photoUrl, 'storage/')) {
                    $localPhotos[] = $photoUrl;
                    continue;
                }

                try {
                    $response = Http::timeout(10)->get($photoUrl);
                    if ($response->successful() && strlen($response->body()) > 100) {
                        $ext = match (true) {
                            str_contains($response->header('Content-Type', ''), 'png') => 'png',
                            str_contains($response->header('Content-Type', ''), 'webp') => 'webp',
                            str_contains($response->header('Content-Type', ''), 'gif') => 'gif',
                            default => 'jpg',
                        };
                        $filename = 'businesses/' . $business->slug . '_' . Str::random(6) . '.' . $ext;
                        Storage::disk('public')->put($filename, $response->body());
                        $localPhotos[] = 'storage/' . $filename;
                    }
                } catch (\Exception $e) {
                    $this->warn("  Failed: {$photoUrl}");
                }
            }

            if (!empty($localPhotos)) {
                $business->update([
                    'photos' => $localPhotos,
                    'photos_downloaded_at' => now(),
                ]);
                $downloaded++;
                $this->info("  OK: {$business->name} (" . count($localPhotos) . " photos)");
            } else {
                $business->update(['photos_downloaded_at' => now()]);
            }
        }

        $this->info("Downloaded photos for {$downloaded} businesses.");
        return 0;
    }
}
