<?php

namespace App\Services;

use App\Models\Business;

class BusinessModuleService
{
    public const DEFINITIONS = [
        'catalog' => [
            'label' => 'Product or menu catalog',
            'description' => 'Show products, menu items, prices, and availability.',
            'depends_on' => [],
        ],
        'orders' => [
            'label' => 'Shopping & COD orders',
            'description' => 'Let customers submit offline/COD orders directly to the business.',
            'depends_on' => ['catalog'],
        ],
        'bookings' => [
            'label' => 'Appointments & bookings',
            'description' => 'Accept booking requests for services, rooms, seats, or appointments.',
            'depends_on' => [],
        ],
        'inventory' => [
            'label' => 'Inventory tracking',
            'description' => 'Track stock and availability for catalog items.',
            'depends_on' => ['catalog'],
        ],
        'transport' => [
            'label' => 'Transport operations',
            'description' => 'Manage vehicles, routes, trips, seats, and operator availability.',
            'depends_on' => [],
        ],
        'turf' => [
            'label' => 'Slot & capacity booking',
            'description' => 'Use time slots and capacity for turfs and similar venues.',
            'depends_on' => ['bookings'],
        ],
    ];

    public function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public function normalize(?array $modules): array
    {
        $normalized = array_fill_keys($this->keys(), false);

        foreach ($modules ?? [] as $key => $value) {
            if (is_int($key)) {
                $key = $value;
                $value = true;
            }

            if (array_key_exists((string) $key, $normalized)) {
                $normalized[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOL);
            }
        }

        foreach ($normalized as $module => $enabled) {
            if (! $enabled) {
                continue;
            }

            foreach (self::DEFINITIONS[$module]['depends_on'] as $dependency) {
                $normalized[$dependency] = true;
            }
        }

        return $normalized;
    }

    public function recommendedFor(Business $business): array
    {
        $subcategory = $business->subcategory;

        if ($subcategory && $subcategory->recommended_modules !== null) {
            return array_keys(array_filter($this->normalize($subcategory->recommended_modules)));
        }

        // Pre-migration fallback: businesses whose subcategory_id still points
        // at a row in the read-only legacy subcategories table.
        $legacySubcategory = $business->legacySubcategory;
        if ($legacySubcategory && $legacySubcategory->recommended_modules !== null) {
            return array_keys(array_filter($this->normalize($legacySubcategory->recommended_modules)));
        }

        // Transitional MODULE default for businesses that have not configured
        // enabled_modules yet (the ~500 AI imports). This is not a world/count
        // source of truth (world = categories.world_id, counts = classifications) —
        // it only seeds sensible module suggestions until the vendor opts in.
        // The legacy 'both' bucket is gone (finalize_category_taxonomy migrates it to 'ordering').
        $recommended = match ($business->category?->module_type) {
            'ordering' => ['catalog', 'orders', 'inventory'],
            'booking' => ['bookings'],
            'transport' => ['transport'],
            'turf' => ['bookings', 'turf'],
            default => [],
        };

        return array_keys(array_filter($this->normalize($recommended)));
    }

    public function effectiveFor(Business $business): array
    {
        if ($business->enabled_modules !== null) {
            return $this->normalize($business->enabled_modules);
        }

        return $this->normalize($this->recommendedFor($business));
    }

    public function update(Business $business, array $modules, ?array $config = null): Business
    {
        $launchControl = app(LaunchControlService::class);
        $globallyEnabled = $launchControl->enabledModuleKeys();

        // Effective capability = Global ∧ Vendor: keys switched off in Launch
        // Controls cannot be enabled here, and their dependencies cannot be
        // auto-enabled through them either.
        $filtered = [];
        foreach ($modules ?? [] as $key => $value) {
            $module = is_int($key) ? $value : $key;
            if (in_array($module, $globallyEnabled, true)) {
                $filtered[$key] = $value;
            }
        }

        $normalized = $this->normalize($filtered);
        foreach ($normalized as $module => $enabled) {
            if (! in_array($module, $globallyEnabled, true)) {
                $normalized[$module] = false;
            }
        }

        $business->forceFill([
            'enabled_modules' => $normalized,
            'module_config' => $config ?? $business->module_config,
            'service_type' => $this->legacyServiceType($normalized),
            'is_bookable' => $normalized['bookings'] || $normalized['transport'],
        ])->save();

        return $business->refresh();
    }

    public function legacyServiceType(array $modules): string
    {
        $modules = $this->normalize($modules);

        if ($modules['transport']) {
            return 'transport';
        }
        if ($modules['turf']) {
            return 'turf';
        }
        if ($modules['orders'] && $modules['bookings']) {
            return 'hybrid';
        }
        if ($modules['orders']) {
            return 'buyable';
        }
        if ($modules['bookings']) {
            return 'bookable';
        }

        return 'directory';
    }

    public function readiness(Business $business): array
    {
        $modules = $this->effectiveFor($business);
        $hasProducts = $business->products()->where('is_active', true)->exists();
        $hasServices = $business->services()->where('is_active', true)->exists();
        $hasVehicles = $business->vehicles()->where('is_active', true)->exists();
        $hasSlots = $business->services()->whereHas('timeSlots', fn ($query) => $query->where('is_active', true))->exists();

        return [
            'catalog' => $this->state($modules['catalog'], $hasProducts, 'Add at least one active product or menu item.'),
            'orders' => $this->state($modules['orders'], $hasProducts, 'Add an active product before accepting orders.'),
            'bookings' => $this->state($modules['bookings'], $hasServices, 'Add at least one active service before accepting bookings.'),
            'inventory' => $this->state($modules['inventory'], $hasProducts, 'Add products before tracking inventory.'),
            'transport' => $this->state($modules['transport'], $hasVehicles, 'Add at least one active vehicle.'),
            'turf' => $this->state($modules['turf'], $hasServices && $hasSlots, 'Add a service and at least one active time slot.'),
        ];
    }

    private function state(bool $enabled, bool $ready, string $nextStep): array
    {
        return [
            'enabled' => $enabled,
            'ready' => ! $enabled || $ready,
            'next_step' => $enabled && ! $ready ? $nextStep : null,
        ];
    }
}
