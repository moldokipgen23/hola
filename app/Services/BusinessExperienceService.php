<?php

namespace App\Services;

use App\Models\Business;
use Carbon\Carbon;

class BusinessExperienceService
{
    public function setAvailabilityMode(Business $business, string $experience, string $mode): Business
    {
        if (! in_array($mode, ['live', 'request', 'contact'], true)) {
            throw new \InvalidArgumentException("Unsupported availability mode: {$mode}");
        }

        $enabledExperiences = $business->enabled_experiences ?? ['directory'];
        if (! in_array($experience, $enabledExperiences, true)) {
            $enabledExperiences[] = $experience;
        }

        $config = $business->experience_config ?? [];
        $config[$experience]['availability_mode'] = $mode;

        $business->forceFill([
            'enabled_experiences' => array_values(array_unique($enabledExperiences)),
            'experience_config' => $config,
            'availability_updated_at' => now(),
            'availability_is_stale' => false,
        ])->save();

        return $business->refresh();
    }

    public function calculateExperienceReadiness(Business $business): array
    {
        $enabledModules = $business->enabled_modules ?? [];
        $enabledExperiences = $business->enabled_experiences ?? ['directory'];
        $experienceConfig = $business->experience_config ?? [];

        $results = [];
        $allExperiences = ['directory', 'retail', 'restaurant', 'appointment', 'stay', 'turf', 'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport', 'seat_event'];

        foreach ($allExperiences as $experience) {
            $enabled = in_array($experience, $enabledExperiences);
            $requiredModule = $this->getRequiredModule($experience);
            $moduleEnabled = $requiredModule ? ($enabledModules[$requiredModule] ?? false) : true;

            $readiness = $this->checkExperienceReadiness($business, $experience, $moduleEnabled);
            $availabilityMode = $this->determineAvailabilityMode($business, $experience, $experienceConfig, $readiness);

            $results[$experience] = [
                'enabled' => $enabled,
                'ready' => $readiness['ready'],
                'availability_mode' => $availabilityMode,
                'missing' => $readiness['missing'],
                'primary_action' => $this->getPrimaryAction($experience, $readiness['ready']),
            ];
        }

        return $results;
    }

    private function getRequiredModule(string $experience): ?string
    {
        return match ($experience) {
            'retail', 'restaurant' => 'catalog',
            'appointment', 'stay', 'seat_event' => 'bookings',
            'turf' => 'turf',
            'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport' => 'transport',
            default => null,
        };
    }

    private function checkExperienceReadiness(Business $business, string $experience, bool $moduleEnabled): array
    {
        $missing = [];

        if (! $moduleEnabled) {
            $missing[] = 'Module not enabled';
        }

        if (! $business->is_active) {
            $missing[] = 'Business inactive';
        }

        $hasInventory = match ($experience) {
            'restaurant', 'retail' => $business->products()->where('is_active', true)->exists(),
            'appointment' => $business->services()->where('booking_mode', 'appointment')->where('is_active', true)->exists(),
            'stay' => $business->services()->where('booking_mode', 'stay')->where('is_active', true)->where('inventory_units', '>', 0)->exists(),
            'turf' => $business->services()->where('booking_mode', 'slot')->where('is_active', true)->where('has_fixed_slots', true)->exists(),
            'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport' => $business->vehicles()->where('is_active', true)->where('is_requestable', true)->where('availability_status', 'available')->exists(),
            'seat_event' => $business->services()->where('booking_mode', 'seat')->where('is_active', true)->where('capacity', '>', 0)->exists(),
            default => true,
        };

        if (! $hasInventory && $experience !== 'directory') {
            $missing[] = 'No active inventory/services/vehicles';
        }

        return [
            'ready' => $moduleEnabled && $hasInventory && $business->is_active,
            'missing' => $missing,
        ];
    }

    private function determineAvailabilityMode(Business $business, string $experience, array $config, array $readiness): string
    {
        if (! $readiness['ready']) {
            return 'contact';
        }

        $explicitMode = $config[$experience]['availability_mode'] ?? null;
        if ($explicitMode && in_array($explicitMode, ['live', 'request', 'contact'])) {
            return $explicitMode;
        }

        // Auto-determine based on freshness
        if ($this->isAvailabilityStale($business, $experience)) {
            return 'request';
        }

        return 'live';
    }

    private function isAvailabilityStale(Business $business, string $experience): bool
    {
        if (! $business->availability_updated_at) {
            return true;
        }

        $updatedAt = Carbon::parse($business->availability_updated_at);
        $freshnessWindow = match ($experience) {
            'taxi' => 2, // hours
            'restaurant', 'retail' => 24, // hours
            'appointment', 'turf' => 72, // hours
            'stay' => 168, // hours (7 days)
            default => 24,
        };

        return $updatedAt->diffInHours(now()) > $freshnessWindow;
    }

    private function getPrimaryAction(string $experience, bool $ready): array
    {
        return match ($experience) {
            'directory' => ['type' => 'contact', 'label' => 'Contact'],
            'restaurant' => ['type' => $ready ? 'order_request' : 'contact', 'label' => $ready ? 'View menu' : 'Contact'],
            'retail' => ['type' => $ready ? 'order_request' : 'contact', 'label' => $ready ? 'Browse products' : 'Contact'],
            'appointment' => ['type' => $ready ? 'booking_request' : 'contact', 'label' => $ready ? 'Book appointment' : 'Contact'],
            'stay' => ['type' => $ready ? 'stay_request' : 'contact', 'label' => $ready ? 'Check availability' : 'Contact'],
            'turf' => ['type' => $ready ? 'slot_booking_request' : 'contact', 'label' => $ready ? 'Book slot' : 'Contact'],
            'taxi' => ['type' => $ready ? 'trip_request' : 'contact', 'label' => $ready ? 'Book ride' : 'Contact'],
            'shared_transport' => ['type' => $ready ? 'trip_request' : 'contact', 'label' => $ready ? 'Book seat' : 'Contact'],
            'vehicle_rental' => ['type' => $ready ? 'rental_request' : 'contact', 'label' => $ready ? 'Rent vehicle' : 'Contact'],
            'goods_transport' => ['type' => $ready ? 'goods_request' : 'contact', 'label' => $ready ? 'Book transport' : 'Contact'],
            'seat_event' => ['type' => $ready ? 'seat_booking_request' : 'contact', 'label' => $ready ? 'Book seats' : 'Contact'],
            default => ['type' => 'contact', 'label' => 'Contact'],
        };
    }

    public function getPrimaryExperienceReadiness(Business $business): ?array
    {
        $readiness = $this->calculateExperienceReadiness($business);
        $primary = $business->primary_experience ?? 'directory';

        return $readiness[$primary] ?? null;
    }

    public function scopeReadyOnly($query, ?string $experience = null)
    {
        return $query->where(function ($q) use ($experience) {
            $experiences = $experience ? [$experience] : ['retail', 'restaurant', 'appointment', 'stay', 'turf', 'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport', 'seat_event'];

            foreach ($experiences as $exp) {
                $requiredModule = $this->getRequiredModule($exp);
                if ($requiredModule) {
                    $q->orWhere("enabled_modules->{$requiredModule}", true);
                }
            }
        })->where('is_active', true);
    }

    public function getPrototypeSummary(Business $business, string $experience): array
    {
        return match ($experience) {
            'restaurant' => [
                'menu_sections_count' => $business->products()->where('is_active', true)->distinct('menu_section')->count('menu_section'),
                'top_items' => $business->products()->where('is_active', true)->orderBy('views_count', 'desc')->limit(5)->get(['id', 'name', 'price', 'image']),
            ],
            'retail' => [
                'categories_count' => $business->products()->where('is_active', true)->distinct('menu_section')->count('menu_section'),
                'top_products' => $business->products()->where('is_active', true)->orderBy('views_count', 'desc')->limit(5)->get(['id', 'name', 'price', 'image']),
            ],
            'appointment' => [
                'next_available_slot' => $this->getNextAvailableSlot($business),
            ],
            'stay' => [
                'min_nightly_price' => $business->services()->where('booking_mode', 'stay')->where('is_active', true)->min('price'),
                'room_types' => $business->services()->where('booking_mode', 'stay')->where('is_active', true)->count(),
            ],
            'turf' => [
                'next_available_slot' => $this->getNextAvailableTurfSlot($business),
                'court_types' => $business->services()->where('booking_mode', 'slot')->where('is_active', true)->count(),
            ],
            'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport' => [
                'vehicle_types' => $business->vehicles()->where('is_active', true)->where('is_requestable', true)->pluck('type', 'service_mode')->unique()->toArray(),
                'min_fare' => $business->vehicles()->where('is_active', true)->where('is_requestable', true)->min('base_fare'),
            ],
            'seat_event' => [
                'seat_categories' => $business->services()->where('booking_mode', 'seat')->where('is_active', true)->pluck('unit_label', 'price_unit')->unique()->toArray(),
                'total_capacity' => $business->services()->where('booking_mode', 'seat')->where('is_active', true)->sum('capacity'),
            ],
            default => [],
        };
    }

    private function getNextAvailableSlot(Business $business): ?string
    {
        $slot = $business->services()
            ->where('booking_mode', 'appointment')
            ->where('is_active', true)
            ->whereHas('timeSlots', function ($q) {
                $q->where('is_active', true)
                    ->where('date', '>=', now()->toDateString())
                    ->where('available', '>', 0)
                    ->orderBy('date')
                    ->orderBy('start_time');
            })
            ->first();

        if (! $slot) {
            return null;
        }

        $nextSlot = $slot->timeSlots()
            ->where('is_active', true)
            ->where('date', '>=', now()->toDateString())
            ->where('available', '>', 0)
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        return $nextSlot ? $nextSlot->date.' '.$nextSlot->start_time : null;
    }

    private function getNextAvailableTurfSlot(Business $business): ?string
    {
        $service = $business->services()
            ->where('booking_mode', 'slot')
            ->where('is_active', true)
            ->whereHas('timeSlots', function ($q) {
                $q->where('is_active', true)
                    ->where('date', '>=', now()->toDateString())
                    ->where('available', '>', 0)
                    ->orderBy('date')
                    ->orderBy('start_time');
            })
            ->first();

        if (! $service) {
            return null;
        }

        $nextSlot = $service->timeSlots()
            ->where('is_active', true)
            ->where('date', '>=', now()->toDateString())
            ->where('available', '>', 0)
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        return $nextSlot ? $nextSlot->date.' '.$nextSlot->start_time.' ('.$service->name.')' : null;
    }
}
