<?php

namespace App\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LaunchControlService
{
    public const CACHE_KEY = 'eiho.launch-control.v1';

    /**
     * These are platform-owned switches. Missing rows deliberately use the
     * listed default so a migration or cache problem cannot accidentally take
     * the existing application offline.
     */
    public const DEFINITIONS = [
        'world.shop' => ['name' => 'Shop', 'group' => 'worlds', 'default' => true, 'description' => 'Shop tab and shopping discovery.'],
        'world.ride' => ['name' => 'Ride', 'group' => 'worlds', 'default' => true, 'description' => 'Ride tab and transport discovery.'],
        'world.book' => ['name' => 'Book', 'group' => 'worlds', 'default' => true, 'description' => 'Book tab and booking discovery.'],
        'world.discover' => ['name' => 'Discover', 'group' => 'worlds', 'default' => true, 'description' => 'Directory and general discovery.'],

        'module.catalog' => ['name' => 'Catalog', 'group' => 'modules', 'default' => true, 'description' => 'Public products and menus.'],
        'module.orders' => ['name' => 'Orders', 'group' => 'modules', 'default' => true, 'description' => 'Offline/COD order requests.'],
        'module.inventory' => ['name' => 'Inventory', 'group' => 'modules', 'default' => true, 'description' => 'Stock-aware shopping.'],
        'module.bookings' => ['name' => 'Bookings', 'group' => 'modules', 'default' => true, 'description' => 'Offline booking requests.'],
        'module.transport' => ['name' => 'Transport', 'group' => 'modules', 'default' => true, 'description' => 'Taxi, shared, rental and goods transport.'],
        'module.turf' => ['name' => 'Turf', 'group' => 'modules', 'default' => true, 'description' => 'Turf venues and time slots.'],

        'experience.directory' => ['name' => 'Directory', 'group' => 'experiences', 'default' => true, 'description' => 'Contact and location listings.'],
        'experience.retail' => ['name' => 'Retail', 'group' => 'experiences', 'default' => true, 'description' => 'Retail storefronts.'],
        'experience.restaurant' => ['name' => 'Restaurant', 'group' => 'experiences', 'default' => true, 'description' => 'Restaurant menus and COD orders.'],
        'experience.appointment' => ['name' => 'Appointments', 'group' => 'experiences', 'default' => true, 'description' => 'Service appointment requests.'],
        'experience.stay' => ['name' => 'Stays', 'group' => 'experiences', 'default' => true, 'description' => 'Hotel and room requests.'],
        'experience.turf' => ['name' => 'Turf booking', 'group' => 'experiences', 'default' => true, 'description' => 'Venue slot booking.'],
        'experience.taxi' => ['name' => 'Taxi', 'group' => 'experiences', 'default' => true, 'description' => 'Point-to-point taxi requests.'],
        'experience.shared_transport' => ['name' => 'Shared transport', 'group' => 'experiences', 'default' => true, 'description' => 'Shared vehicle and seat requests.'],
        'experience.vehicle_rental' => ['name' => 'Vehicle rental', 'group' => 'experiences', 'default' => true, 'description' => 'Vehicle rental requests.'],
        'experience.goods_transport' => ['name' => 'Goods transport', 'group' => 'experiences', 'default' => true, 'description' => 'Goods vehicle requests.'],
        'experience.seat_event' => ['name' => 'Seat events', 'group' => 'experiences', 'default' => true, 'description' => 'Seat-based event booking.'],

        'payments.online' => ['name' => 'Online payments', 'group' => 'payments', 'default' => false, 'description' => 'Reserved for a future online-payment launch.'],
    ];

    private const EXPERIENCE_REQUIREMENTS = [
        'directory' => ['world' => 'discover', 'modules' => []],
        'retail' => ['world' => 'shop', 'modules' => ['catalog']],
        'restaurant' => ['world' => 'shop', 'modules' => ['catalog']],
        'appointment' => ['world' => 'book', 'modules' => ['bookings']],
        'stay' => ['world' => 'book', 'modules' => ['bookings']],
        'turf' => ['world' => 'book', 'modules' => ['bookings', 'turf']],
        'seat_event' => ['world' => 'book', 'modules' => ['bookings']],
        // Phase 3: the Ride bucket is folded into Booking — transport
        // experiences gate under `book`.
        'taxi' => ['world' => 'book', 'modules' => ['transport']],
        'shared_transport' => ['world' => 'book', 'modules' => ['transport']],
        'vehicle_rental' => ['world' => 'book', 'modules' => ['transport']],
        'goods_transport' => ['world' => 'book', 'modules' => ['transport']],
    ];

    public function enabled(string $key): bool
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        $flag = $this->flags()[$key] ?? null;

        if (! $flag) {
            return (bool) ($definition['default'] ?? false);
        }

        return (bool) $flag['is_enabled'] && (bool) $flag['is_visible_to_customers'];
    }

    public function worldEnabled(string $slug): bool
    {
        return $this->enabled("world.$slug");
    }

    public function worldAvailable(string $slug): bool
    {
        if (! $this->worldEnabled($slug)) {
            return false;
        }

        return match ($slug) {
            'shop' => $this->moduleEnabled('catalog') && ($this->experienceEnabled('retail') || $this->experienceEnabled('restaurant')),
            // Phase 3: Booking owns both appointment-style and transport
            // experiences; the standalone `ride` bucket no longer exists.
            'book' => ($this->moduleEnabled('bookings') && collect(['appointment', 'stay', 'turf', 'seat_event'])
                ->contains(fn (string $experience) => $this->experienceEnabled($experience)))
                || ($this->moduleEnabled('transport') && collect(['taxi', 'shared_transport', 'vehicle_rental', 'goods_transport'])
                    ->contains(fn (string $experience) => $this->experienceEnabled($experience))),
            'discover' => $this->enabled('experience.directory'),
            default => false,
        };
    }

    public function moduleEnabled(string $module): bool
    {
        return $this->enabled("module.$module");
    }

    public function experienceEnabled(string $experience): bool
    {
        if (! $this->enabled("experience.$experience")) {
            return false;
        }

        $requirements = self::EXPERIENCE_REQUIREMENTS[$experience] ?? null;
        if (! $requirements) {
            return false;
        }

        if (! $this->worldEnabled($requirements['world'])) {
            return false;
        }

        foreach ($requirements['modules'] as $module) {
            if (! $this->moduleEnabled($module)) {
                return false;
            }
        }

        return true;
    }

    public function filterExperiences(array $experiences): array
    {
        return array_values(array_filter($experiences, fn (string $experience) => $this->experienceEnabled($experience)));
    }

    public function publicConfig(): array
    {
        $worlds = [];
        $modules = [];
        $experiences = [];

        foreach (array_keys(self::DEFINITIONS) as $key) {
            [$group, $name] = explode('.', $key, 2);
            if ($group === 'world') {
                $worlds[$name] = $this->worldAvailable($name);
            } elseif ($group === 'module') {
                $modules[$name] = $this->moduleEnabled($name);
            } elseif ($group === 'experience') {
                $experiences[$name] = $this->experienceEnabled($name);
            }
        }

        return [
            'worlds' => $worlds,
            'modules' => $modules,
            'experiences' => $experiences,
            'payments' => ['online' => $this->enabled('payments.online')],
            'enabled_tabs' => array_values(array_keys(array_filter($worlds))),
            'updated_at' => collect($this->flags())->max('updated_at'),
        ];
    }

    public function syncDefinitions(): void
    {
        foreach (self::DEFINITIONS as $key => $definition) {
            FeatureFlag::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'group' => $definition['group'],
                    'is_enabled' => $definition['default'],
                    'is_visible_to_customers' => true,
                    'metadata' => ['system' => true],
                ],
            );
        }
        self::clearCache();
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function flags(): array
    {
        if (! Schema::hasTable('feature_flags')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn () => FeatureFlag::query()
            ->get(['key', 'is_enabled', 'is_visible_to_customers', 'updated_at'])
            ->keyBy('key')
            ->map(fn (FeatureFlag $flag) => [
                'is_enabled' => $flag->is_enabled,
                'is_visible_to_customers' => $flag->is_visible_to_customers,
                'updated_at' => $flag->updated_at?->toIso8601String(),
            ])
            ->all());
    }
}
