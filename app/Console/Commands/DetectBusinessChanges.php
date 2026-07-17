<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DetectBusinessChanges extends Command
{
    protected $signature = 'app:detect-business-changes {--limit=50} {--dry-run}';

    protected $description = 'Check Google Places API for changes in existing businesses and notify admin';

    public function handle()
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Get businesses with external_id (imported from Google)
        $businesses = Business::whereNotNull('external_id')
            ->where('source', 'google_places')
            ->where('is_active', true)
            ->limit($limit)
            ->get();

        $this->info("Checking {$businesses->count()} businesses for changes...");

        $changes = [];
        $updated = 0;

        foreach ($businesses as $business) {
            try {
                $placeData = $this->getGooglePlaceData($business->external_id);

                if (! $placeData) {
                    continue;
                }

                $businessChanges = $this->detectChanges($business, $placeData);

                if (! empty($businessChanges)) {
                    $changes[] = [
                        'business_id' => $business->id,
                        'business_name' => $business->name,
                        'changes' => $businessChanges,
                    ];

                    if (! $dryRun) {
                        $this->updateBusiness($business, $placeData, $businessChanges);
                        $updated++;
                    }
                }

                // Rate limiting - 100ms between requests
                usleep(100000);

            } catch (\Exception $e) {
                $this->error("Error checking {$business->name}: {$e->getMessage()}");

                continue;
            }
        }

        // Output results
        if (empty($changes)) {
            $this->info('No changes detected.');

            return 0;
        }

        $this->info("\n=== Changes Detected ===\n");

        foreach ($changes as $change) {
            $this->warn("Business: {$change['business_name']} (ID: {$change['business_id']})");
            foreach ($change['changes'] as $field => $changeDetail) {
                $this->line("  - {$field}: \"{$changeDetail['old']}\" → \"{$changeDetail['new']}\"");
            }
            $this->newLine();
        }

        // Save changes log
        if (! $dryRun) {
            $logPath = storage_path('app/business_changes_log.json');
            $existingLog = file_exists($logPath) ? json_decode(file_get_contents($logPath), true) : [];
            $existingLog[] = [
                'timestamp' => now()->toIso8601String(),
                'changes_count' => count($changes),
                'changes' => $changes,
            ];
            file_put_contents($logPath, json_encode($existingLog, JSON_PRETTY_PRINT));

            $this->info("Changes logged to: {$logPath}");
            $this->info("Updated {$updated} businesses.");
        } else {
            $this->warn('DRY RUN - No changes were saved.');
        }

        return 0;
    }

    private function getGooglePlaceData(string $placeId): ?array
    {
        $apiKey = config('services.google.places_api_key');

        if (! $apiKey) {
            return null;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,formatted_phone_number,website,rating,user_ratings_total,opening_hours,photos,reviews,types,geometry',
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return $data['result'] ?? null;
    }

    private function detectChanges(Business $business, array $placeData): array
    {
        $changes = [];

        // Check name changes
        if (! empty($placeData['name']) && $placeData['name'] !== $business->name) {
            $changes['name'] = [
                'old' => $business->name,
                'new' => $placeData['name'],
            ];
        }

        // Check address changes
        if (! empty($placeData['formatted_address']) && $placeData['formatted_address'] !== $business->address) {
            $changes['address'] = [
                'old' => $business->address,
                'new' => $placeData['formatted_address'],
            ];
        }

        // Check phone changes
        $newPhone = $placeData['formatted_phone_number'] ?? null;
        if ($newPhone && $newPhone !== $business->phone) {
            $changes['phone'] = [
                'old' => $business->phone,
                'new' => $newPhone,
            ];
        }

        // Check website changes
        $newWebsite = $placeData['website'] ?? null;
        if ($newWebsite && $newWebsite !== $business->website) {
            $changes['website'] = [
                'old' => $business->website,
                'new' => $newWebsite,
            ];
        }

        // Check rating changes
        $newRating = $placeData['rating'] ?? null;
        if ($newRating && abs($newRating - ($business->average_rating ?? 0)) > 0.1) {
            $changes['rating'] = [
                'old' => $business->average_rating ?? 0,
                'new' => $newRating,
            ];
        }

        // Check review count changes
        $newReviewCount = $placeData['user_ratings_total'] ?? 0;
        if ($newReviewCount > 0 && $newReviewCount !== ($business->review_count ?? 0)) {
            $changes['review_count'] = [
                'old' => $business->review_count ?? 0,
                'new' => $newReviewCount,
            ];
        }

        return $changes;
    }

    private function updateBusiness(Business $business, array $placeData, array $changes): void
    {
        $updateData = [];

        if (isset($changes['name'])) {
            $updateData['name'] = $changes['name']['new'];
            $updateData['slug'] = Str::slug($changes['name']['new']);
        }

        if (isset($changes['address'])) {
            $updateData['address'] = $changes['address']['new'];
        }

        if (isset($changes['phone'])) {
            $updateData['phone'] = $changes['phone']['new'];
        }

        if (isset($changes['website'])) {
            $updateData['website'] = $changes['website']['new'];
        }

        if (isset($changes['rating'])) {
            $updateData['average_rating'] = $changes['rating']['new'];
        }

        if (isset($changes['review_count'])) {
            $updateData['review_count'] = $changes['review_count']['new'];
        }

        // Update photos if available
        if (! empty($placeData['photos'])) {
            $photos = [];
            foreach (array_slice($placeData['photos'], 0, 5) as $photo) {
                if (! empty($photo['photo_reference'])) {
                    $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photo['photo_reference']}&maxwidth=800&key=".config('services.google.places_api_key');
                    try {
                        $response = Http::timeout(10)->get($photoUrl);
                        if ($response->successful()) {
                            $filename = 'businesses/'.$business->slug.'_'.Str::random(6).'.jpg';
                            Storage::disk('public')->put($filename, $response->body());
                            $photos[] = 'storage/'.$filename;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            if (! empty($photos)) {
                $updateData['photos'] = array_merge($business->photos ?? [], $photos);
                // Keep only latest 10 photos
                $updateData['photos'] = array_slice($updateData['photos'], -10);
            }
        }

        if (! empty($updateData)) {
            $updateData['last_synced_at'] = now();
            $business->update($updateData);
        }
    }
}
