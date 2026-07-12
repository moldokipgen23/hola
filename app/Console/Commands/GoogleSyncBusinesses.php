<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleSyncBusinesses extends Command
{
    protected $signature = 'google:sync {--limit=10} {--business-id=}';
    protected $description = 'Sync imported businesses with Google Places — detect changes, update info, track closures';

    public function handle(): int
    {
        $apiKey = Setting::get('api_key_google_places') ?? config('services.google.places_api_key');
        if (!$apiKey) {
            $this->error('Google Places API key not configured.');
            return 1;
        }

        $query = Business::where('source', 'import')
            ->where('is_active', true)
            ->whereNotNull('external_id');

        if ($this->option('business-id')) {
            $query->where('id', $this->option('business-id'));
        }

        $businesses = $query->limit($this->option('limit'))->get();

        if ($businesses->isEmpty()) {
            $this->info('No imported businesses to sync.');
            return 0;
        }

        $this->info("🔄 Syncing " . $businesses->count() . " businesses with Google...");

        $agent = AiAgent::where('status', 'active')->first();
        $updated = 0;
        $changed = 0;
        $closed = 0;
        $errors = 0;
        $changes = [];

        foreach ($businesses as $business) {
            try {
                $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $business->external_id,
                    'fields' => 'name,formatted_phone_number,website,opening_hours,photos,rating,user_ratings_total,formatted_address,geometry,reviews,business_status',
                    'key' => $apiKey,
                ]);

                if ($response->failed()) {
                    $errors++;
                    continue;
                }

                $data = $response->json('result', []);
                if (empty($data)) {
                    $errors++;
                    continue;
                }

                $businessChanges = [];
                $newData = [];

                // Check if business is permanently closed
                if (($data['business_status'] ?? '') === 'CLOSED_PERMANENTLY') {
                    $business->update(['is_active' => false]);
                    $closed++;
                    $this->warn("  ❌ CLOSED: {$business->name}");
                    continue;
                }

                // Check name change
                $newName = $data['name'] ?? null;
                if ($newName && $newName !== $business->name) {
                    $businessChanges[] = "name: \"{$business->name}\" → \"{$newName}\"";
                    $newData['name'] = $newName;
                }

                // Check phone change
                $newPhone = $data['formatted_phone_number'] ?? null;
                if ($newPhone && $newPhone !== $business->phone) {
                    $businessChanges[] = "phone: \"{$business->phone}\" → \"{$newPhone}\"";
                    $newData['phone'] = $newPhone;
                }

                // Check website change
                $newWebsite = $data['website'] ?? null;
                if ($newWebsite && $newWebsite !== $business->website) {
                    $businessChanges[] = "website updated";
                    $newData['website'] = $newWebsite;
                }

                // Check address change
                $newAddress = $data['formatted_address'] ?? null;
                if ($newAddress && $newAddress !== $business->address) {
                    $businessChanges[] = "address changed";
                    $newData['address'] = $newAddress;
                }

                // Check rating change
                $newRating = $data['rating'] ?? null;
                $newReviewCount = $data['user_ratings_total'] ?? 0;
                if ($newRating && ($newRating != $business->average_rating || $newReviewCount != $business->review_count)) {
                    $businessChanges[] = "rating: {$business->average_rating} → {$newRating} ({$newReviewCount} reviews)";
                    $newData['average_rating'] = $newRating;
                    $newData['review_count'] = $newReviewCount;
                }

                // Check location change
                $newLat = $data['geometry']['location']['lat'] ?? null;
                $newLng = $data['geometry']['location']['lng'] ?? null;
                if ($newLat && $newLng && ($newLat != $business->latitude || $newLng != $business->longitude)) {
                    $businessChanges[] = "location changed";
                    $newData['latitude'] = $newLat;
                    $newData['longitude'] = $newLng;
                }

                // Check working hours change
                $workingHours = null;
                if (!empty($data['opening_hours']['weekday_text'])) {
                    $hoursMap = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    $workingHours = [];
                    foreach ($data['opening_hours']['weekday_text'] as $index => $text) {
                        $day = strtolower($hoursMap[$index] ?? '');
                        if (Str::contains($text, 'Closed')) {
                            $workingHours[$day] = ['open' => null, 'close' => null];
                        } else {
                            $parts = explode(':', $text, 2);
                            $times = trim($parts[1] ?? '');
                            $timeParts = explode('–', $times);
                            $workingHours[$day] = [
                                'open' => trim($timeParts[0] ?? ''),
                                'close' => trim($timeParts[1] ?? ''),
                            ];
                        }
                    }
                    if ($workingHours != $business->working_hours) {
                        $businessChanges[] = "working hours updated";
                        $newData['working_hours'] = $workingHours;
                    }
                }

                // Check photos (new photos added?)
                if (!empty($data['photos'])) {
                    $currentPhotoCount = count($business->photos ?? []);
                    $googlePhotoCount = count($data['photos']);
                    if ($googlePhotoCount > $currentPhotoCount) {
                        $newPhotos = [];
                        foreach (array_slice($data['photos'], 0, 10) as $photo) {
                            if (!empty($photo['photo_reference'])) {
                                $newPhotos[] = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photo['photo_reference']}&maxwidth=800&key={$apiKey}";
                            }
                        }
                        if (!empty($newPhotos)) {
                            $businessChanges[] = "photos updated ({$googlePhotoCount} on Google)";
                            $newData['photos'] = $newPhotos;
                        }
                    }
                }

                // Apply changes
                if (!empty($businessChanges)) {
                    $newData['last_synced_at'] = now();
                    $business->update($newData);
                    $changed++;
                    $changes[] = [
                        'business' => $business->name,
                        'changes' => $businessChanges,
                    ];
                    $this->info("  ✏️  {$business->name}: " . implode(', ', $businessChanges));
                } else {
                    // No changes — just update last_synced_at
                    $business->update(['last_synced_at' => now()]);
                }

                $updated++;

                // Rate limit: 100ms between requests
                usleep(100000);

            } catch (\Exception $e) {
                $errors++;
                $this->warn("  ⚠️  Error syncing {$business->name}: {$e->getMessage()}");
            }
        }

        // Log the sync task
        if ($agent) {
            AiAgentTask::create([
                'agent_id' => $agent->id,
                'type' => 'google_sync',
                'input' => ['limit' => $this->option('limit')],
                'output' => [
                    'synced' => $updated,
                    'changed' => $changed,
                    'closed' => $closed,
                    'errors' => $errors,
                    'changes' => $changes,
                ],
                'status' => 'completed',
                'result_count' => $updated,
                'imported_count' => $changed,
            ]);
        }

        $this->info("");
        $this->info("📊 Sync complete:");
        $this->info("  Synced: {$updated}");
        $this->info("  Changed: {$changed}");
        $this->info("  Closed: {$closed}");
        $this->info("  Errors: {$errors}");

        return 0;
    }
}
