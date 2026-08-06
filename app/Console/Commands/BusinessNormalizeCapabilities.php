<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\BusinessModuleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('business:normalize-capabilities {--apply : Actually write changes (default is dry-run)}')]
#[Description('Normalize all business capability JSONs and assign experience types. Dry-run by default.')]
class BusinessNormalizeCapabilities extends Command
{
    public function handle(): int
    {
        $dryRun = ! $this->option('apply');

        if ($dryRun) {
            $this->warn('DRY-RUN MODE — no changes will be written. Use --apply to persist.');
        }

        $this->info('Loading businesses...');
        $businesses = Business::with('category')->get();
        $this->info("Found {$businesses->count()} businesses.");

        $moduleService = app(BusinessModuleService::class);

        $counts = [
            'total' => 0,
            'normalized' => 0,
            'experience_assigned' => 0,
            'contradictions_fixed' => 0,
            'ambiguous_queued' => 0,
            'unchanged' => 0,
        ];

        $ambiguous = [];

        foreach ($businesses as $business) {
            $counts['total']++;

            // Skip unclaimed (AI-imported) businesses — they must stay
            // directory-only until a vendor claims them and picks their
            // module type in the onboarding wizard.
            if ($business->claim_status === 'unclaimed') {
                $counts['unchanged']++;

                continue;
            }

            // 1. Normalize enabled_modules
            $rawModules = $business->enabled_modules ?? [];
            $normalized = $moduleService->normalize($rawModules);

            $modulesChanged = false;
            if ($rawModules !== $normalized) {
                $modulesChanged = true;
                $this->line("  [{$business->id}] {$business->name}: enabled_modules normalized");
            }

            // 2. Recalculate service_type and is_bookable
            $legacyServiceType = $moduleService->legacyServiceType($normalized);
            $legacyIsBookable = $normalized['bookings'] || $normalized['transport'];

            $serviceTypeChanged = $business->service_type !== $legacyServiceType;
            $isBookableChanged = $business->is_bookable !== $legacyIsBookable;

            if ($serviceTypeChanged) {
                $this->line("  [{$business->id}] {$business->name}: service_type {$business->service_type} -> {$legacyServiceType}");
            }
            if ($isBookableChanged) {
                $this->line("  [{$business->id}] {$business->name}: is_bookable ".($business->is_bookable ? 'true' : 'false').' -> '.($legacyIsBookable ? 'true' : 'false'));
            }

            // 3. Assign experience types from category/subcategory
            $experienceResult = $this->assignExperiences($business, $normalized);
            if ($experienceResult['assigned']) {
                $counts['experience_assigned']++;
                $this->line("  [{$business->id}] {$business->name}: primary_experience={$experienceResult['primary']}, enabled=".json_encode($experienceResult['enabled']));
            }
            if ($experienceResult['ambiguous']) {
                $counts['ambiguous_queued']++;
                $ambiguous[] = [
                    'id' => $business->id,
                    'name' => $business->name,
                    'category' => $business->category?->name,
                    'subcategory' => $business->subcategory?->name,
                    'suggested_primary' => $experienceResult['primary'],
                    'suggested_enabled' => $experienceResult['enabled'],
                ];
            }

            // 4. Track contradictions
            if ($modulesChanged || $serviceTypeChanged || $isBookableChanged) {
                $counts['contradictions_fixed']++;
            }

            $anyChange = $modulesChanged || $serviceTypeChanged || $isBookableChanged || $experienceResult['assigned'];
            if ($anyChange) {
                $counts['normalized']++;

                if (! $dryRun) {
                    DB::transaction(function () use ($business, $normalized, $legacyServiceType, $legacyIsBookable, $experienceResult) {
                        $business->enabled_modules = $normalized;
                        $business->service_type = $legacyServiceType;
                        $business->is_bookable = $legacyIsBookable;
                        $business->primary_experience = $experienceResult['primary'];
                        $business->enabled_experiences = $experienceResult['enabled'];
                        $business->experience_config = $this->buildExperienceConfig($experienceResult['enabled']);
                        $business->saveQuietly();
                    });
                }
            } else {
                $counts['unchanged']++;
            }
        }

        $this->printSummary($counts, $ambiguous, $dryRun);

        return Command::SUCCESS;
    }

    private function assignExperiences(Business $business, array $normalized): array
    {
        $category = $business->category?->name;
        $subcategory = $business->subcategory?->name;

        $mapping = $this->experienceMapping();

        $key = strtolower(trim(($subcategory ?? $category) ?? ''));
        $rule = $mapping[$key] ?? $mapping[strtolower($category ?? '')] ?? null;

        $primary = 'directory';
        $enabled = ['directory'];
        $config = [];

        if ($rule) {
            $primary = $rule['primary'];
            $enabled = array_unique(array_merge(['directory'], $rule['enabled'] ?? []));
            $config = $this->buildExperienceConfig($enabled);
        }

        $assigned = $business->primary_experience !== $primary
            || ($business->enabled_experiences ?? []) !== $enabled
            || ($business->experience_config ?? []) !== $config;

        $ambiguous = ! $rule && ($normalized['orders'] || $normalized['bookings'] || $normalized['transport'] || $normalized['turf']);

        return [
            'primary' => $primary,
            'enabled' => $enabled,
            'config' => $config,
            'assigned' => $assigned,
            'ambiguous' => $ambiguous,
        ];
    }

    private function buildExperienceConfig(array $enabled): array
    {
        $config = [];
        foreach ($enabled as $exp) {
            $mode = match ($exp) {
                'restaurant' => 'request',
                'retail' => 'request',
                'appointment' => 'request',
                'stay' => 'request',
                'turf' => 'request',
                'taxi' => 'request',
                'shared_transport' => 'request',
                'vehicle_rental' => 'request',
                'goods_transport' => 'request',
                'seat_event' => 'request',
                default => 'contact',
            };
            $config[$exp] = ['availability_mode' => $mode];
        }

        return $config;
    }

    private function experienceMapping(): array
    {
        return [
            // Food & Restaurants
            'restaurant' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'food & restaurants' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'cafe' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'bakery' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'fast food' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'street food' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],
            'catering' => ['primary' => 'restaurant', 'enabled' => ['restaurant']],

            // Retail
            'grocery' => ['primary' => 'retail', 'enabled' => ['retail']],
            'supermarket' => ['primary' => 'retail', 'enabled' => ['retail']],
            'grocery stores' => ['primary' => 'retail', 'enabled' => ['retail']],
            'clothing' => ['primary' => 'retail', 'enabled' => ['retail']],
            'electronics' => ['primary' => 'retail', 'enabled' => ['retail']],
            'shopping' => ['primary' => 'retail', 'enabled' => ['retail']],
            'pharmacy' => ['primary' => 'retail', 'enabled' => ['retail']],
            'medical store' => ['primary' => 'retail', 'enabled' => ['retail']],
            'pharmacies' => ['primary' => 'retail', 'enabled' => ['retail']],
            'hardware' => ['primary' => 'retail', 'enabled' => ['retail']],
            'hardware stores' => ['primary' => 'retail', 'enabled' => ['retail']],
            'stationery' => ['primary' => 'retail', 'enabled' => ['retail']],
            'mobile shops' => ['primary' => 'retail', 'enabled' => ['retail']],
            'computer stores' => ['primary' => 'retail', 'enabled' => ['retail']],
            'repair shops' => ['primary' => 'retail', 'enabled' => ['retail']],

            // Appointment services
            'salon' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'beauty' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'beauty parlours' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'spa' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'spas' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'clinic' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'clinics' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'hospital' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'hospitals' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'dental' => ['primary' => 'appointment', 'enabled' => ['appointment']],
            'diagnostic' => ['primary' => 'appointment', 'enabled' => ['appointment']],

            // Stay / Hotels
            'hotel' => ['primary' => 'stay', 'enabled' => ['stay']],
            'hotels' => ['primary' => 'stay', 'enabled' => ['stay']],
            'resort' => ['primary' => 'stay', 'enabled' => ['stay']],
            'guest house' => ['primary' => 'stay', 'enabled' => ['stay']],
            'guest houses' => ['primary' => 'stay', 'enabled' => ['stay']],
            'homestay' => ['primary' => 'stay', 'enabled' => ['stay']],
            'homestays' => ['primary' => 'stay', 'enabled' => ['stay']],
            'hotels & lodges' => ['primary' => 'stay', 'enabled' => ['stay']],

            // Turf
            'football turf' => ['primary' => 'turf', 'enabled' => ['turf']],
            'turf' => ['primary' => 'turf', 'enabled' => ['turf']],
            'badminton court' => ['primary' => 'turf', 'enabled' => ['turf']],
            'cricket net' => ['primary' => 'turf', 'enabled' => ['turf']],
            'swimming pool' => ['primary' => 'turf', 'enabled' => ['turf']],

            // Transport
            'taxi' => ['primary' => 'taxi', 'enabled' => ['taxi']],
            'cab' => ['primary' => 'taxi', 'enabled' => ['taxi']],
            'auto' => ['primary' => 'taxi', 'enabled' => ['taxi']],
            'bike taxi' => ['primary' => 'taxi', 'enabled' => ['taxi']],
            'bus' => ['primary' => 'shared_transport', 'enabled' => ['shared_transport']],
            'shared transport' => ['primary' => 'shared_transport', 'enabled' => ['shared_transport']],
            'rental' => ['primary' => 'vehicle_rental', 'enabled' => ['vehicle_rental']],
            'goods transport' => ['primary' => 'goods_transport', 'enabled' => ['goods_transport']],
            'truck' => ['primary' => 'goods_transport', 'enabled' => ['goods_transport']],
            'tempo' => ['primary' => 'goods_transport', 'enabled' => ['goods_transport']],
            'pickup' => ['primary' => 'goods_transport', 'enabled' => ['goods_transport']],

            // Seat Events
            'event' => ['primary' => 'seat_event', 'enabled' => ['seat_event']],
            'theatre' => ['primary' => 'seat_event', 'enabled' => ['seat_event']],
            'cinema' => ['primary' => 'seat_event', 'enabled' => ['seat_event']],
            'concert' => ['primary' => 'seat_event', 'enabled' => ['seat_event']],

            // Education (directory mostly)
            'tuition centers' => ['primary' => 'directory', 'enabled' => ['directory']],
            'school' => ['primary' => 'directory', 'enabled' => ['directory']],
            'college' => ['primary' => 'directory', 'enabled' => ['directory']],
            'preschool' => ['primary' => 'directory', 'enabled' => ['directory']],

            // Professional Services (directory mostly)
            'bank' => ['primary' => 'directory', 'enabled' => ['directory']],
            'banks' => ['primary' => 'directory', 'enabled' => ['directory']],
            'insurance' => ['primary' => 'directory', 'enabled' => ['directory']],
            'legal services' => ['primary' => 'directory', 'enabled' => ['directory']],
            'travel agents' => ['primary' => 'directory', 'enabled' => ['directory']],

            // Fitness (directory mostly)
            'gym' => ['primary' => 'directory', 'enabled' => ['directory']],
            'gyms' => ['primary' => 'directory', 'enabled' => ['directory']],
            'fitness' => ['primary' => 'directory', 'enabled' => ['directory']],
            'sports shops' => ['primary' => 'retail', 'enabled' => ['retail']],

            // Automobiles (mostly directory)
            'service centers' => ['primary' => 'directory', 'enabled' => ['directory']],
            'car dealers' => ['primary' => 'directory', 'enabled' => ['directory']],
            'bike shops' => ['primary' => 'directory', 'enabled' => ['directory']],

            // Beauty (appointment)
            'salons' => ['primary' => 'appointment', 'enabled' => ['appointment']],
        ];
    }

    private function printSummary(array $counts, array $ambiguous, bool $dryRun): void
    {
        $this->newLine();
        $this->info('=== NORMALIZATION SUMMARY ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total businesses', $counts['total']],
                ['Normalized (would change)', $counts['normalized']],
                ['Experience types assigned', $counts['experience_assigned']],
                ['Contradictions fixed', $counts['contradictions_fixed']],
                ['Ambiguous (queued for review)', $counts['ambiguous_queued']],
                ['Unchanged', $counts['unchanged']],
            ]
        );

        if (! empty($ambiguous)) {
            $this->warn("\nAmbiguous businesses requiring manual review ({$counts['ambiguous_queued']}):");
            foreach ($ambiguous as $a) {
                $this->line("  ID {$a['id']}: {$a['name']} (cat: {$a['category']}, sub: {$a['subcategory']})");
                $this->line("    -> suggested primary: {$a['suggested_primary']}, enabled: ".json_encode($a['suggested_enabled']));
            }
        }

        if ($dryRun) {
            $this->warn("\nDry-run complete. Re-run with --apply to persist changes.");
        } else {
            $this->info("\nChanges persisted.");
        }
    }
}
