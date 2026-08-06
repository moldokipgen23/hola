<?php

namespace App\Services\Experience;

use App\Models\Business;
use App\Models\TimeSlot;
use App\Services\BusinessModuleService;
use Carbon\Carbon;

class BusinessExperienceService
{
    private BusinessModuleService $moduleService;

    public function __construct(BusinessModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    public function calculateReadiness(Business $business): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        $enabledExperiences = $business->enabled_experiences ?? ['directory'];
        $experienceConfig = $business->experience_config ?? [];

        $readiness = [];

        foreach ($enabledExperiences as $experience) {
            $readiness[$experience] = $this->calculateExperienceReadiness($business, $experience, $modules, $experienceConfig[$experience] ?? []);
        }

        return $readiness;
    }

    public function calculateReadinessForExperiences(Business $business, array $experiences): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        $experienceConfig = $business->experience_config ?? [];

        $readiness = [];

        foreach ($experiences as $experience) {
            $readiness[$experience] = $this->calculateExperienceReadiness($business, $experience, $modules, $experienceConfig[$experience] ?? []);
        }

        return $readiness;
    }

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

    public function getPrimaryExperienceReadiness(Business $business): ?array
    {
        $readiness = $this->calculateReadiness($business);
        $primary = $business->primary_experience ?? 'directory';

        return $readiness[$primary] ?? null;
    }

    public function scopeReadyOnly($query, ?string $experience = null)
    {
        return $query->where(function ($q) use ($experience) {
            $experiences = $experience
                ? [$experience]
                : ['retail', 'restaurant', 'appointment', 'stay', 'turf', 'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport', 'seat_event'];

            foreach ($experiences as $exp) {
                $requiredModule = $this->requiredModuleFor($exp);
                if ($requiredModule) {
                    $q->orWhere("enabled_modules->{$requiredModule}", true);
                }
            }
        })->where('is_active', true);
    }

    private function requiredModuleFor(string $experience): ?string
    {
        return match ($experience) {
            'retail', 'restaurant' => 'catalog',
            'appointment', 'stay', 'seat_event' => 'bookings',
            'turf' => 'turf',
            'taxi', 'shared_transport', 'vehicle_rental', 'goods_transport' => 'transport',
            default => null,
        };
    }

    private function calculateExperienceReadiness(Business $business, string $experience, array $modules, array $config): array
    {
        $readiness = [
            'enabled' => true,
            'ready' => false,
            'availability_mode' => 'contact',
            'missing' => [],
        ];

        switch ($experience) {
            case 'directory':
                $readiness['ready'] = true;
                $readiness['availability_mode'] = 'contact';
                break;

            case 'restaurant':
                $readiness = $this->checkRestaurantReadiness($business, $readiness);
                break;

            case 'retail':
                $readiness = $this->checkRetailReadiness($business, $readiness);
                break;

            case 'appointment':
                $readiness = $this->checkAppointmentReadiness($business, $readiness);
                break;

            case 'stay':
                $readiness = $this->checkStayReadiness($business, $readiness);
                break;

            case 'turf':
                $readiness = $this->checkTurfReadiness($business, $readiness);
                break;

            case 'taxi':
                $readiness = $this->checkTaxiReadiness($business, $readiness);
                break;

            case 'shared_transport':
                $readiness = $this->checkSharedTransportReadiness($business, $readiness);
                break;

            case 'vehicle_rental':
                $readiness = $this->checkRentalReadiness($business, $readiness);
                break;

            case 'goods_transport':
                $readiness = $this->checkGoodsTransportReadiness($business, $readiness);
                break;

            case 'seat_event':
                $readiness = $this->checkSeatEventReadiness($business, $readiness);
                break;
        }

        if ($experience !== 'directory' && $readiness['ready'] && $readiness['availability_mode'] === 'contact') {
            $readiness['availability_mode'] = 'request';
        }

        // Apply vendor-configured availability mode
        $vendorMode = $config['availability_mode'] ?? null;
        if ($vendorMode && in_array($vendorMode, ['live', 'request', 'contact'])) {
            $readiness['availability_mode'] = $vendorMode;
        }

        // Auto-downgrade stale live to request
        if ($readiness['availability_mode'] === 'live' && $this->isAvailabilityStale($business, $experience)) {
            $readiness['availability_mode'] = 'request';
            $readiness['missing'][] = 'availability_stale';
        }

        return $readiness;
    }

    private function checkRestaurantReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['catalog']) {
            $readiness['missing'][] = 'catalog_module';

            return $readiness;
        }
        if (! $modules['orders']) {
            $readiness['missing'][] = 'orders_module';
        }

        $products = $business->products()->where('is_active', true)->count();
        if ($products === 0) {
            $readiness['missing'][] = 'no_active_products';
        }

        $hasProductsWithPrice = $business->products()
            ->where('is_active', true)
            ->whereNotNull('price')
            ->exists();
        if (! $hasProductsWithPrice) {
            $readiness['missing'][] = 'products_missing_price';
        }

        $readiness['ready'] = empty(array_diff($readiness['missing'], ['orders_module']));
        if (empty($readiness['missing'])) {
            $readiness['availability_mode'] = $readiness['availability_mode'] === 'live' ? 'live' : 'request';
        }

        return $readiness;
    }

    private function checkRetailReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['catalog']) {
            $readiness['missing'][] = 'catalog_module';

            return $readiness;
        }
        if (! $modules['orders']) {
            $readiness['missing'][] = 'orders_module';
        }

        $products = $business->products()->where('is_active', true)->count();
        if ($products === 0) {
            $readiness['missing'][] = 'no_active_products';
        }

        $readiness['ready'] = empty(array_diff($readiness['missing'], ['orders_module']));

        return $readiness;
    }

    private function checkAppointmentReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['bookings']) {
            $readiness['missing'][] = 'bookings_module';

            return $readiness;
        }

        $services = $business->services()->where('is_active', true)->count();
        if ($services === 0) {
            $readiness['missing'][] = 'no_active_services';
        }

        $appointmentServices = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'appointment')
            ->count();
        if ($appointmentServices === 0) {
            $readiness['missing'][] = 'no_appointment_services';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkStayReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['bookings']) {
            $readiness['missing'][] = 'bookings_module';

            return $readiness;
        }

        $stayServices = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'stay')
            ->count();
        if ($stayServices === 0) {
            $readiness['missing'][] = 'no_stay_services';
        }

        $hasInventory = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'stay')
            ->where('inventory_units', '>', 0)
            ->exists();
        if (! $hasInventory) {
            $readiness['missing'][] = 'no_inventory_units';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkTurfReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['bookings'] || ! $modules['turf']) {
            $readiness['missing'][] = ($modules['bookings'] ? '' : 'bookings_module').($modules['turf'] ? '' : 'turf_module');

            return $readiness;
        }

        $slotServices = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'slot')
            ->count();
        if ($slotServices === 0) {
            $readiness['missing'][] = 'no_slot_services';
        }

        $hasSlots = TimeSlot::whereHas('service', function ($q) use ($business) {
            $q->where('business_id', $business->id);
        })->where('is_active', true)->exists();

        if (! $hasSlots) {
            $readiness['missing'][] = 'no_time_slots';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkTaxiReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['transport']) {
            $readiness['missing'][] = 'transport_module';

            return $readiness;
        }

        $taxiVehicles = $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'taxi')
            ->where('is_requestable', true)
            ->count();
        if ($taxiVehicles === 0) {
            $readiness['missing'][] = 'no_taxi_vehicles';
        }

        $hasPricing = $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'taxi')
            ->where('base_fare', '>', 0)
            ->exists();
        if (! $hasPricing) {
            $readiness['missing'][] = 'no_vehicle_pricing';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkSharedTransportReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['transport']) {
            $readiness['missing'][] = 'transport_module';

            return $readiness;
        }

        $sharedVehicles = $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'shared')
            ->where('is_requestable', true)
            ->count();
        if ($sharedVehicles === 0) {
            $readiness['missing'][] = 'no_shared_vehicles';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkRentalReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['transport']) {
            $readiness['missing'][] = 'transport_module';

            return $readiness;
        }

        $rentalVehicles = $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'rental')
            ->where('is_requestable', true)
            ->count();
        if ($rentalVehicles === 0) {
            $readiness['missing'][] = 'no_rental_vehicles';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkGoodsTransportReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['transport']) {
            $readiness['missing'][] = 'transport_module';

            return $readiness;
        }

        $goodsVehicles = $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'goods')
            ->where('is_requestable', true)
            ->count();
        if ($goodsVehicles === 0) {
            $readiness['missing'][] = 'no_goods_vehicles';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function checkSeatEventReadiness(Business $business, array $readiness): array
    {
        $modules = $this->moduleService->effectiveFor($business);
        if (! $modules['bookings']) {
            $readiness['missing'][] = 'bookings_module';

            return $readiness;
        }

        $seatServices = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'seat')
            ->count();
        if ($seatServices === 0) {
            $readiness['missing'][] = 'no_seat_services';
        }

        $hasCapacity = $business->services()
            ->where('is_active', true)
            ->where('booking_mode', 'seat')
            ->where('capacity', '>', 0)
            ->exists();
        if (! $hasCapacity) {
            $readiness['missing'][] = 'no_seat_capacity';
        }

        $readiness['ready'] = empty($readiness['missing']);

        return $readiness;
    }

    private function isAvailabilityStale(Business $business, string $experience): bool
    {
        $updatedAt = $business->availability_updated_at ?? $business->updated_at;
        if (! $updatedAt) {
            return true;
        }

        $freshnessHours = match ($experience) {
            'taxi' => 1,
            'restaurant' => 12,
            'retail' => 24,
            'turf' => 72,
            'appointment' => 72,
            'stay' => 168, // 7 days
            'shared_transport' => 24,
            'vehicle_rental' => 168,
            'goods_transport' => 24,
            'seat_event' => 168,
            default => 72,
        };

        return $updatedAt->diffInHours(now()) > $freshnessHours;
    }

    public function getPrimaryAction(Business $business): array
    {
        $readiness = $this->calculateReadiness($business);
        $primaryExperience = $business->primary_experience ?? 'directory';
        $experienceReadiness = $readiness[$primaryExperience] ?? ['ready' => false, 'availability_mode' => 'contact'];

        return [
            'type' => $this->actionTypeForExperience($primaryExperience, $experienceReadiness),
            'label' => $this->actionLabelForExperience($primaryExperience, $experienceReadiness),
        ];
    }

    private function actionTypeForExperience(string $experience, array $readiness): string
    {
        if ($experience === 'directory') {
            return 'contact';
        }
        if (! $readiness['ready']) {
            return 'contact';
        }

        return match ($readiness['availability_mode']) {
            'live' => $this->liveActionType($experience),
            'request' => $this->requestActionType($experience),
            default => 'contact',
        };
    }

    private function liveActionType(string $experience): string
    {
        return match ($experience) {
            'taxi' => 'book_ride',
            'restaurant', 'retail' => 'order_now',
            'turf' => 'book_slot',
            'appointment' => 'book_appointment',
            'stay' => 'check_availability',
            'shared_transport' => 'book_seat',
            'vehicle_rental' => 'check_availability',
            'goods_transport' => 'get_quote',
            'seat_event' => 'select_seats',
            default => 'contact',
        };
    }

    private function requestActionType(string $experience): string
    {
        return match ($experience) {
            'taxi' => 'request_ride',
            'restaurant', 'retail' => 'request_order',
            'turf' => 'request_slot',
            'appointment' => 'request_appointment',
            'stay' => 'request_stay',
            'shared_transport' => 'request_seat',
            'vehicle_rental' => 'request_quote',
            'goods_transport' => 'request_quote',
            'seat_event' => 'request_seats',
            default => 'contact',
        };
    }

    private function actionLabelForExperience(string $experience, array $readiness): string
    {
        if ($experience === 'directory') {
            return 'Contact';
        }
        if (! $readiness['ready']) {
            return 'Contact to confirm';
        }

        return match ($readiness['availability_mode']) {
            'live' => $this->liveActionLabel($experience),
            'request' => $this->requestActionLabel($experience),
            default => 'Contact',
        };
    }

    private function liveActionLabel(string $experience): string
    {
        return match ($experience) {
            'taxi' => 'Book Ride',
            'restaurant' => 'Order Food',
            'retail' => 'Order Now',
            'turf' => 'Book Turf',
            'appointment' => 'Book Appointment',
            'stay' => 'Check Availability',
            'shared_transport' => 'Book Seat',
            'vehicle_rental' => 'Check Availability',
            'goods_transport' => 'Get Quote',
            'seat_event' => 'Select Seats',
            default => 'Book',
        };
    }

    private function requestActionLabel(string $experience): string
    {
        return match ($experience) {
            'taxi' => 'Request Ride',
            'restaurant' => 'Request Order',
            'retail' => 'Request Order',
            'turf' => 'Request Slot',
            'appointment' => 'Request Appointment',
            'stay' => 'Request Stay',
            'shared_transport' => 'Request Seat',
            'vehicle_rental' => 'Request Quote',
            'goods_transport' => 'Request Quote',
            'seat_event' => 'Request Seats',
            default => 'Contact',
        };
    }

    public function getPrototypeSummary(Business $business, string $experience): array
    {
        return match ($experience) {
            'restaurant' => [
                'menu_sections_count' => $business->products()->where('is_active', true)->distinct('menu_section')->count('menu_section'),
                'top_items' => $business->products()->where('is_active', true)->orderByDesc('views_count')->limit(5)->get(['id', 'name', 'price', 'image']),
            ],
            'retail' => [
                'categories_count' => $business->products()->where('is_active', true)->distinct('menu_section')->count('menu_section'),
                'top_products' => $business->products()->where('is_active', true)->orderByDesc('views_count')->limit(5)->get(['id', 'name', 'price', 'image']),
            ],
            'appointment' => ['next_available_slot' => $this->getNextAvailableSlot($business, 'appointment')],
            'stay' => [
                'min_nightly_price' => $business->services()->where('booking_mode', 'stay')->where('is_active', true)->min('price'),
                'room_types' => $business->services()->where('booking_mode', 'stay')->where('is_active', true)->count(),
            ],
            'turf' => [
                'next_available_slot' => $this->getNextAvailableSlot($business, 'slot'),
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

    private function getNextAvailableSlot(Business $business, string $bookingMode): ?string
    {
        $service = $business->services()
            ->where('booking_mode', $bookingMode)
            ->where('is_active', true)
            ->whereHas('timeSlots', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $service) {
            return null;
        }

        $advanceDays = max(1, (int) ($service->advance_booking_days ?? 60));
        $slots = $service->timeSlots()->where('is_active', true)->get();

        for ($offset = 0; $offset < $advanceDays; $offset++) {
            $date = today()->addDays($offset);
            $dayOfWeek = $date->dayOfWeek;

            foreach ($slots as $slot) {
                if ($slot->day_of_week !== null && $slot->day_of_week !== $dayOfWeek) {
                    continue;
                }
                if ($offset === 0 && Carbon::parse($date->toDateString().' '.$slot->start_time)->isPast()) {
                    continue;
                }
                if ($slot->availableSlots($date->toDateString()) > 0) {
                    return $date->toDateString().' '.$slot->start_time;
                }
            }
        }

        return null;
    }
}
