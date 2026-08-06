<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Setting;
use App\Services\BunnyStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadImportPhotos extends Command
{
    protected $signature = 'photos:download {--limit=10} {--business-id=} {--force}';

    protected $description = 'Download external photos for imported businesses to cloud or local storage';

    public function handle()
    {
        $useBunny = BunnyStorage::isConfigured();
        $this->info('Storage: '.($useBunny ? 'Bunny CDN ('.BunnyStorage::getCdnUrl().')' : 'Local VPS disk'));

        $query = Business::where('source', 'import')
            ->whereNotNull('photos');

        if (! $this->option('force')) {
            // Default: only businesses not yet processed.
            $query->whereNull('photos_downloaded_at');
        } else {
            // --force: reprocess businesses that still hold Google URLs, even
            // if a previous (failed) run already stamped photos_downloaded_at.
            $query->where(function ($q) {
                $q->where('photos', 'like', '%googleapis%')
                    ->orWhere('photos', 'like', '%photo_reference%');
            });
        }

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
            if (! is_array($photos) || empty($photos)) {
                if (! $this->option('force')) {
                    $business->update(['photos_downloaded_at' => now()]);
                }

                continue;
            }

            // If the stored photos are Google-sourced and we know the place,
            // fetch FRESH photo references first — stored references expire and
            // downloading them would just fail (or produce broken images).
            $urls = $photos;
            if ($business->external_id && $this->isGoogleSourced($photos)) {
                $fresh = $this->freshGoogleUrls($business);
                if ($fresh) {
                    $urls = $fresh;
                    $this->info("  ↻ refreshed refs for {$business->name}");
                }
                usleep(200000); // respect Places API rate limits
            }

            $savedPhotos = [];
            foreach ($urls as $photoUrl) {
                // Photo stored as a photo_reference array (from Google Places).
                // Build the URL server-side with the key so it never leaves the server.
                if (is_array($photoUrl) && ! empty($photoUrl['photo_reference'])) {
                    $apiKey = Setting::get('api_key_google_places') ?? config('services.google.places_api_key');
                    if ($apiKey) {
                        $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photoUrl['photo_reference']}&maxwidth=800&key={$apiKey}";
                    } else {
                        continue;
                    }
                }

                // Already a local or CDN URL — skip
                if (str_starts_with($photoUrl, 'storage/') || str_starts_with($photoUrl, 'http')) {
                    // External URL needs downloading
                    if (str_starts_with($photoUrl, 'http') && ! str_contains($photoUrl, '.b-cdn.net')) {
                        try {
                            $response = Http::timeout(10)->get($photoUrl);
                            if ($response->successful() && strlen($response->body()) > 100) {
                                $ext = match (true) {
                                    str_contains($response->header('Content-Type', ''), 'png') => 'png',
                                    str_contains($response->header('Content-Type', ''), 'webp') => 'webp',
                                    str_contains($response->header('Content-Type', ''), 'gif') => 'gif',
                                    default => 'jpg',
                                };
                                $filename = 'businesses/'.$business->slug.'_'.Str::random(6).'.'.$ext;

                                if ($useBunny) {
                                    BunnyStorage::put($filename, $response->body());
                                    $savedPhotos[] = BunnyStorage::getPublicUrl($filename);
                                } else {
                                    Storage::disk('public')->put($filename, $response->body());
                                    $savedPhotos[] = 'storage/'.$filename;
                                }
                            }
                        } catch (\Exception $e) {
                            $this->warn("  Failed: {$photoUrl}");
                        }
                    } else {
                        // Already saved (CDN URL or local path)
                        $savedPhotos[] = $photoUrl;
                    }
                }
            }

            if (! empty($savedPhotos)) {
                $business->update([
                    'photos' => $savedPhotos,
                    'photos_downloaded_at' => now(),
                ]);
                $downloaded++;
                $this->info("  OK: {$business->name} (".count($savedPhotos).' photos)');
            } elseif (! $this->option('force')) {
                // Mark as attempted so we don't retry every run by default.
                $business->update(['photos_downloaded_at' => now()]);
            }
            // --force + nothing saved: leave untouched so a future run retries.
        }

        $this->info("Downloaded photos for {$downloaded} businesses.");

        return 0;
    }

    private function isGoogleSourced(array $photos): bool
    {
        foreach ($photos as $photo) {
            if (is_array($photo)) {
                return true; // photo_reference array
            }
            if (is_string($photo) && str_contains($photo, 'maps.googleapis.com')) {
                return true;
            }
        }

        return false;
    }

    private function freshGoogleUrls(Business $business): ?array
    {
        $placeId = $business->external_id;
        $apiKey = Setting::get('api_key_google_places') ?? config('services.google.places_api_key');
        if (! $placeId || ! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get(
                'https://maps.googleapis.com/maps/api/place/details/json',
                [
                    'place_id' => $placeId,
                    'fields' => 'photos',
                    'key' => $apiKey,
                ]
            );
        } catch (\Exception $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $photos = $response->json('result.photos') ?? [];
        $urls = [];
        foreach (array_slice($photos, 0, 6) as $photo) {
            if (! empty($photo['photo_reference'])) {
                $urls[] = 'https://maps.googleapis.com/maps/api/place/photo'
                    .'?photoreference='.rawurlencode($photo['photo_reference'])
                    .'&maxwidth=800&key='.$apiKey;
            }
        }

        return $urls ?: null;
    }
}
