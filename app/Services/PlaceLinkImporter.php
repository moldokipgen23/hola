<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceLinkImporter
{
    /**
     * Extract a Google Place ID from a Maps share/detail link.
     * Handles: https://maps.app.goo.gl/..., place/search/...ChIJ..., ?q=..., etc.
     */
    public function extractPlaceId(string $link): ?string
    {
        $decoded = urldecode(trim($link));

        // Common forms:
        //  https://www.google.com/maps/place/NAME/@lat,lng,17z/data=...!3m3!1sChIJ...
        //  https://maps.google.com/?q=ChIJ...
        //  https://www.google.com/maps/search/?api=1&query=ChIJ...
        //  https://maps.app.goo.gl/XXXX  (short link — must be resolved)
        if (preg_match('/1s(ChIJ[0-9A-Za-z_-]+)/', $decoded, $m)) {
            return $m[1];
        }
        if (preg_match('/place\/(ChIJ[0-9A-Za-z_-]+)/', $decoded, $m)) {
            return $m[1];
        }
        if (preg_match('/[?&]query=([^&]+)/', $decoded, $m) && Str::startsWith($m[1], 'ChIJ')) {
            return $m[1];
        }
        if (preg_match('/[?&]q=([^&]+)/', $decoded, $m) && Str::startsWith($m[1], 'ChIJ')) {
            return $m[1];
        }

        return null;
    }

    /**
     * Fetch a place by ID from the Google Places Details API.
     */
    public function fetchPlace(string $placeId): ?array
    {
        $apiKey = Setting::get('api_key_google_places')
            ?? config('services.google.places_api_key');
        if (! $apiKey) {
            throw ValidationException::withMessages(['link' => 'Google Places API key is not configured.']);
        }

        $response = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,formatted_phone_number,international_phone_number,website,url,geometry,photos,rating,user_ratings_total,types,opening_hours,address_components',
            'key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages(['link' => 'Google Places API request failed.']);
        }

        $data = $response->json();
        if (($data['status'] ?? '') !== 'OK' || empty($data['result'])) {
            throw ValidationException::withMessages(['link' => 'No business found for this link. Check the link and try again.']);
        }

        return $data['result'];
    }

    /**
     * Import a place by link into the review queue. Returns the ImportItem.
     */
    public function importByLink(string $link, ?int $cityId = null, ?int $agentId = null): ImportItem
    {
        $placeId = $this->extractPlaceId($link);
        if (! $placeId) {
            throw ValidationException::withMessages(['link' => 'Could not read a Google Place ID from that link. Copy the full Share link from Google Maps.']);
        }

        $place = $this->fetchPlace($placeId);

        // Duplicate check by google place id.
        $existing = ImportItem::where('external_id', $placeId)->first();
        if ($existing) {
            throw ValidationException::withMessages(['link' => 'This business is already in the import queue.']);
        }

        $name = $place['name'] ?? 'Unknown Business';
        $address = $place['formatted_address'] ?? '';
        $lat = $place['geometry']['location']['lat'] ?? null;
        $lng = $place['geometry']['location']['lng'] ?? null;

        // Resolve locality/district from address components.
        $locality = null;
        $district = null;
        $state = null;
        foreach ($place['address_components'] ?? [] as $component) {
            $types = $component['types'] ?? [];
            $nameComponent = $component['long_name'] ?? null;
            if (in_array('locality', $types)) {
                $locality = $nameComponent;
            }
            if (in_array('administrative_area_level_2', $types)) {
                $district = $nameComponent;
            }
            if (in_array('administrative_area_level_1', $types)) {
                $state = $nameComponent;
            }
        }

        // Build photos as photo_reference (never embed API keys).
        $photos = [];
        foreach (array_slice($place['photos'] ?? [], 0, 6) as $photo) {
            if (! empty($photo['photo_reference'])) {
                $photos[] = ['photo_reference' => $photo['photo_reference']];
            }
        }

        $data = [
            'name' => $name,
            'address' => $address,
            'locality' => $locality,
            'district' => $district,
            'state' => $state,
            'latitude' => $lat,
            'longitude' => $lng,
            'phone' => $place['international_phone_number'] ?? $place['formatted_phone_number'] ?? null,
            'website' => $place['website'] ?? null,
            'google_maps_url' => $place['url'] ?? null,
            'rating' => $place['rating'] ?? 0,
            'total_ratings' => $place['user_ratings_total'] ?? 0,
            'types' => $place['types'] ?? [],
            'working_hours' => $this->parseHours($place['opening_hours'] ?? null),
            'photos' => $photos,
            'source' => 'link_import',
            'city_id' => $cityId,
        ];

        $batch = ImportBatch::firstOrCreate(
            ['name' => 'Manual link import'],
            ['agent_id' => $agentId, 'source' => 'google_places', 'status' => 'processing', 'total' => 0],
        );

        $item = ImportItem::create([
            'batch_id' => $batch->id,
            'data' => $data,
            'external_id' => $placeId,
            'status' => 'pending',
            'confidence' => 0.9,
            'notes' => 'Imported from a shared Google Maps link.',
        ]);

        $batch->increment('total');

        return $item;
    }

    private function parseHours(?array $openingHours): ?array
    {
        if (empty($openingHours['weekday_text'])) {
            return null;
        }

        $days = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
        $hours = [];
        foreach ($openingHours['weekday_text'] as $line) {
            $parts = explode(':', $line, 2);
            $dayName = trim($parts[0] ?? '');
            $range = trim($parts[1] ?? '');
            $dayNum = $days[$dayName] ?? null;
            if ($dayNum !== null) {
                if (str_contains(strtolower($range), 'closed')) {
                    $hours[$dayNum] = ['open' => null, 'close' => null];
                } else {
                    $times = explode('–', str_replace('–', '–', $range), 2);
                    $hours[$dayNum] = [
                        'open' => trim($times[0] ?? ''),
                        'close' => trim($times[1] ?? ''),
                    ];
                }
            }
        }

        return $hours ?: null;
    }
}
