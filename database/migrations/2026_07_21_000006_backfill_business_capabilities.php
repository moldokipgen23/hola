<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULES = ['catalog', 'orders', 'bookings', 'inventory', 'transport', 'turf'];

    public function up(): void
    {
        $categories = DB::table('categories')->pluck('module_type', 'id');
        $subcategories = DB::table('subcategories')->pluck('recommended_modules', 'id');

        DB::table('businesses')
            ->whereNull('enabled_modules')
            ->orderBy('id')
            ->chunkById(200, function ($businesses) use ($categories, $subcategories) {
                foreach ($businesses as $business) {
                    $recommended = null;

                    if ($business->subcategory_id && $subcategories->has($business->subcategory_id)) {
                        $stored = $subcategories->get($business->subcategory_id);
                        $recommended = $stored === null ? null : (json_decode($stored, true) ?: []);
                    }

                    if ($recommended === null) {
                        $recommended = match ($categories->get($business->category_id)) {
                            'ordering' => ['catalog', 'orders', 'inventory'],
                            'booking' => ['bookings'],
                            'both' => ['catalog', 'orders', 'inventory', 'bookings'],
                            'transport' => ['transport'],
                            'turf' => ['bookings', 'turf'],
                            default => [],
                        };
                    }

                    $modules = $this->normalize($recommended);

                    DB::table('businesses')->where('id', $business->id)->update([
                        'enabled_modules' => json_encode($modules),
                        'service_type' => $this->serviceType($modules),
                        'is_bookable' => $modules['bookings'] || $modules['transport'],
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Capability choices are business data and must not be erased on rollback.
    }

    private function normalize(array $recommended): array
    {
        $modules = array_fill_keys(self::MODULES, false);

        foreach ($recommended as $key => $value) {
            if (is_int($key)) {
                $key = $value;
                $value = true;
            }

            if (array_key_exists((string) $key, $modules)) {
                $modules[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOL);
            }
        }

        if ($modules['orders'] || $modules['inventory']) {
            $modules['catalog'] = true;
        }
        if ($modules['turf']) {
            $modules['bookings'] = true;
        }

        return $modules;
    }

    private function serviceType(array $modules): string
    {
        return match (true) {
            $modules['transport'] => 'transport',
            $modules['turf'] => 'turf',
            $modules['orders'] && $modules['bookings'] => 'hybrid',
            $modules['orders'] => 'buyable',
            $modules['bookings'] => 'bookable',
            default => 'directory',
        };
    }
};
