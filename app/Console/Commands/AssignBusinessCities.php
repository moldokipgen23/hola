<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignBusinessCities extends Command
{
    protected $signature = 'businesses:assign-cities {--dry-run : Report what would change without writing}';

    protected $description = 'Assign city_id + pincode to businesses based on their address/district/lat-lng';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $cities = City::where('is_active', true)->get();
        if ($cities->isEmpty()) {
            $this->error('No active cities found. Run the IndiaCitySeeder first.');

            return 1;
        }

        $businesses = Business::whereNull('city_id')->get();
        $updated = 0;

        foreach ($businesses as $business) {
            $city = $this->resolveCity($business, $cities);
            if ($city === null) {
                continue;
            }

            $updates = ['city_id' => $city->id];
            if (empty($business->pincode)) {
                $updates['pincode'] = $city->pincode;
            }

            if ($dryRun) {
                $this->line("  [dry-run] {$business->id} {$business->name} -> {$city->name}");
                continue;
            }

            $business->update($updates);
            $updated++;
            $this->line("  ✓ {$business->id} {$business->name} -> {$city->name}");
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Assigned cities to {$updated} businesses.");

        return 0;
    }

    private function resolveCity(Business $business, iterable $cities): ?City
    {
        $cities = collect($cities);
        $haystack = mb_strtolower(implode(' ', array_filter([
            $business->district,
            $business->address,
            $business->locality,
            $business->state,
            $business->pincode,
        ])));

        // 1. Match by district name
        foreach ($cities as $city) {
            if ($city->district && mb_strtolower($city->district) !== ''
                && str_contains($haystack, mb_strtolower($city->district))) {
                return $city;
            }
        }

        // 2. Match by city name / slug fragment in the address
        foreach ($cities as $city) {
            $needles = array_filter([
                mb_strtolower($city->name),
                mb_strtolower($city->slug),
                mb_strtolower($city->state ?? ''),
            ]);
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return $city;
                }
            }
        }

        // 3. Nearest city by coordinates (businesses usually have lat/lng)
        if ($business->latitude && $business->longitude) {
            $nearest = null;
            $nearestDist = PHP_FLOAT_MAX;
            foreach ($cities as $city) {
                if (! $city->latitude || ! $city->longitude) {
                    continue;
                }
                $dist = $this->haversine(
                    (float) $business->latitude,
                    (float) $business->longitude,
                    (float) $city->latitude,
                    (float) $city->longitude,
                );
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearest = $city;
                }
            }
            if ($nearest && $nearestDist <= 150) { // within ~150km
                return $nearest;
            }
        }

        return null;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
