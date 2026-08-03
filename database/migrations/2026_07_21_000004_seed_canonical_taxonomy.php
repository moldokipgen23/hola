<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $newCategories = [
            ['Food & Restaurants', 'food-restaurants', '🍽️', 'ordering', 1],
            ['Hotels & Lodges', 'hotels-lodges', '🏨', 'booking', 2],
            ['Healthcare', 'healthcare', '🏥', 'booking', 3],
            ['Education', 'education', '📚', 'booking', 4],
            ['Shopping & Retail', 'shopping-retail', '🛍️', 'ordering', 5],
            ['Electronics & Tech', 'electronics-tech', '💻', 'ordering', 6],
            ['Automobiles', 'automobiles', '🚗', 'booking', 7],
            ['Beauty & Wellness', 'beauty-wellness', '💆', 'booking', 8],
            ['Professional Services', 'professional-services', '💼', 'directory', 9],
            ['Sports & Fitness', 'sports-fitness', '🏋️', 'booking', 10],
            ['Transport', 'transport', '🚕', 'directory', 11],
            ['Government & Public Services', 'government-public-services', '🏛️', 'directory', 12],
            ['Religious & Community Places', 'religious-community-places', '⛪', 'directory', 13],
            ['Tourism & Attractions', 'tourism-attractions', '🗺️', 'directory', 14],
            ['Home & Local Services', 'home-local-services', '🛠️', 'booking', 15],
        ];

        foreach ($newCategories as [$name, $slug, $icon, $moduleType, $order]) {
            $this->upsertPreservingCreatedAt(
                'categories',
                ['slug' => $slug],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'module_type' => $moduleType,
                    'order' => $order,
                    'is_active' => true,
                    'is_featured' => false,
                    'is_canonical' => true,
                ],
                $now,
            );
        }

        $subcategoryDefinitions = [
            'food-restaurants' => [
                ['Restaurants', ['catalog', 'orders']],
                ['Street Food', ['catalog', 'orders']],
                ['Bakeries', ['catalog', 'orders']],
                ['Cafes', ['catalog', 'orders']],
                ['Fast Food', ['catalog', 'orders']],
                ['Catering', ['catalog', 'orders']],
            ],
            'hotels-lodges' => [
                ['Hotels', ['bookings']],
                ['Guest Houses', ['bookings']],
                ['Homestays', ['bookings']],
                ['Resorts', ['bookings']],
            ],
            'healthcare' => [
                ['Hospitals', []],
                ['Pharmacies', ['catalog', 'orders']],
                ['Clinics', ['bookings']],
                ['Dental', ['bookings']],
                ['Diagnostic Lab', ['bookings']],
            ],
            'education' => [
                ['Schools', []],
                ['Colleges', []],
                ['Tuition Centers', ['bookings']],
                ['Preschools', []],
                ['Music & Dance Schools', ['bookings']],
            ],
            'shopping-retail' => [
                ['Grocery Stores', ['catalog', 'orders']],
                ['Clothing', ['catalog', 'orders']],
                ['Hardware Stores', ['catalog', 'orders']],
                ['Stationery', ['catalog', 'orders']],
                ['Shopping Mall', ['catalog', 'orders']],
                ['Electronics Store', ['catalog', 'orders']],
            ],
            'electronics-tech' => [
                ['Mobile Shops', ['catalog', 'orders']],
                ['Computer Stores', ['catalog', 'orders']],
                ['Repair Shops', ['bookings']],
            ],
            'automobiles' => [
                ['Car Dealers', ['catalog']],
                ['Bike Shops', ['catalog']],
                ['Service Centers', ['bookings']],
            ],
            'beauty-wellness' => [
                ['Salons', ['bookings']],
                ['Spas', ['bookings']],
                ['Beauty Parlours', ['bookings']],
            ],
            'professional-services' => [
                ['Banks', []],
                ['Insurance', []],
                ['Legal Services', ['bookings']],
                ['Travel Agents', ['bookings']],
            ],
            'sports-fitness' => [
                ['Gyms', ['bookings']],
                ['Sports Shops', ['catalog', 'orders']],
                ['Football Turf', ['bookings', 'turf']],
                ['Swimming Pool', ['bookings']],
                ['Picnic Spot', []],
                ['Amusement Park', []],
            ],
            'transport' => [
                ['Taxi Services', ['transport']],
                ['Bus & Shared Transport', ['transport']],
                ['Truck & Goods Transport', ['transport']],
                ['Vehicle Rentals', ['transport']],
            ],
            'government-public-services' => [
                ['Government Offices', []],
                ['Police Services', []],
                ['Fire & Emergency Services', []],
                ['Post Offices', []],
                ['Public Utilities', []],
            ],
            'religious-community-places' => [
                ['Churches', []],
                ['Temples', []],
                ['Mosques', []],
                ['Community Organizations', []],
            ],
            'tourism-attractions' => [
                ['Tourist Attractions', []],
                ['Parks', []],
                ['Museums & Galleries', []],
            ],
            'home-local-services' => [
                ['Plumbers', ['bookings']],
                ['Electricians', ['bookings']],
                ['Cleaning Services', ['bookings']],
                ['Construction & Contractors', ['bookings']],
            ],
        ];

        foreach ($subcategoryDefinitions as $categorySlug => $subcategories) {
            $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id');

            if (! $categoryId) {
                continue;
            }

            foreach ($subcategories as $index => [$name, $modules]) {
                $this->upsertPreservingCreatedAt(
                    'subcategories',
                    ['category_id' => $categoryId, 'slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'order' => $index + 1,
                        'is_active' => true,
                        'recommended_modules' => json_encode($modules),
                    ],
                    $now,
                );
            }
        }

        $this->setExistingRecommendations();
        $this->seedGoogleMappings();
    }

    public function down(): void
    {
        DB::table('source_taxonomy_mappings')->where('provider', 'google_places')->delete();

        DB::table('subcategories')->whereIn('slug', [
            'preschools',
            'music-dance-schools',
            'taxi-services',
            'bus-shared-transport',
            'truck-goods-transport',
            'vehicle-rentals',
            'government-offices',
            'police-services',
            'fire-emergency-services',
            'post-offices',
            'public-utilities',
            'churches',
            'temples',
            'mosques',
            'community-organizations',
            'tourist-attractions',
            'parks',
            'museums-galleries',
            'plumbers',
            'electricians',
            'cleaning-services',
            'construction-contractors',
        ])->delete();

        DB::table('categories')->whereIn('slug', [
            'transport',
            'government-public-services',
            'religious-community-places',
            'tourism-attractions',
            'home-local-services',
        ])->delete();
    }

    private function setExistingRecommendations(): void
    {
        $recommendations = [
            'restaurants' => ['catalog', 'orders'],
            'street-food' => ['catalog', 'orders'],
            'bakeries' => ['catalog', 'orders'],
            'cafes' => ['catalog', 'orders'],
            'fast-food' => ['catalog', 'orders'],
            'hotels' => ['bookings'],
            'guest-houses' => ['bookings'],
            'homestays' => ['bookings'],
            'resorts' => ['bookings'],
            'hospitals' => [],
            'pharmacies' => ['catalog', 'orders'],
            'clinics' => ['bookings'],
            'dental' => ['bookings'],
            'schools' => [],
            'colleges' => [],
            'tuition-centers' => ['bookings'],
            'grocery-stores' => ['catalog', 'orders'],
            'clothing' => ['catalog', 'orders'],
            'hardware-stores' => ['catalog', 'orders'],
            'stationery' => ['catalog', 'orders'],
            'mobile-shops' => ['catalog', 'orders'],
            'computer-stores' => ['catalog', 'orders'],
            'repair-shops' => ['bookings'],
            'car-dealers' => ['catalog'],
            'bike-shops' => ['catalog'],
            'service-centers' => ['bookings'],
            'salons' => ['bookings'],
            'spas' => ['bookings'],
            'beauty-parlours' => ['bookings'],
            'banks' => [],
            'insurance' => [],
            'legal-services' => ['bookings'],
            'travel-agents' => ['bookings'],
            'gyms' => ['bookings'],
            'sports-shops' => ['catalog', 'orders'],
        ];

        foreach ($recommendations as $slug => $modules) {
            DB::table('subcategories')
                ->where('slug', $slug)
                ->update(['recommended_modules' => json_encode($modules)]);
        }
    }

    private function seedGoogleMappings(): void
    {
        $mappings = [
            'restaurant' => ['food-restaurants', 'restaurants', ['catalog', 'orders']],
            'cafe' => ['food-restaurants', 'cafes', ['catalog', 'orders']],
            'bakery' => ['food-restaurants', 'bakeries', ['catalog', 'orders']],
            'meal_takeaway' => ['food-restaurants', 'fast-food', ['catalog', 'orders']],
            'lodging' => ['hotels-lodges', 'hotels', ['bookings']],
            'hotel' => ['hotels-lodges', 'hotels', ['bookings']],
            'hospital' => ['healthcare', 'hospitals', []],
            'doctor' => ['healthcare', 'clinics', ['bookings']],
            'dentist' => ['healthcare', 'dental', ['bookings']],
            'pharmacy' => ['healthcare', 'pharmacies', ['catalog', 'orders']],
            'preschool' => ['education', 'preschools', []],
            'school' => ['education', 'schools', []],
            'university' => ['education', 'colleges', []],
            'supermarket' => ['shopping-retail', 'grocery-stores', ['catalog', 'orders']],
            'clothing_store' => ['shopping-retail', 'clothing', ['catalog', 'orders']],
            'hardware_store' => ['shopping-retail', 'hardware-stores', ['catalog', 'orders']],
            'electronics_store' => ['electronics-tech', 'mobile-shops', ['catalog', 'orders']],
            'car_repair' => ['automobiles', 'service-centers', ['bookings']],
            'car_dealer' => ['automobiles', 'car-dealers', ['catalog']],
            'beauty_salon' => ['beauty-wellness', 'salons', ['bookings']],
            'spa' => ['beauty-wellness', 'spas', ['bookings']],
            'gym' => ['sports-fitness', 'gyms', ['bookings']],
            'stadium' => ['sports-fitness', 'football-turf', ['bookings', 'turf']],
            'taxi_stand' => ['transport', 'taxi-services', ['transport']],
            'car_rental' => ['transport', 'vehicle-rentals', ['transport']],
            'bus_station' => ['transport', 'bus-shared-transport', ['transport']],
            'local_government_office' => ['government-public-services', 'government-offices', []],
            'police' => ['government-public-services', 'police-services', []],
            'fire_station' => ['government-public-services', 'fire-emergency-services', []],
            'post_office' => ['government-public-services', 'post-offices', []],
            'church' => ['religious-community-places', 'churches', []],
            'place_of_worship' => ['religious-community-places', null, []],
            'tourist_attraction' => ['tourism-attractions', 'tourist-attractions', []],
            'park' => ['tourism-attractions', 'parks', []],
            'museum' => ['tourism-attractions', 'museums-galleries', []],
            'art_gallery' => ['tourism-attractions', 'museums-galleries', []],
            'plumber' => ['home-local-services', 'plumbers', ['bookings']],
            'electrician' => ['home-local-services', 'electricians', ['bookings']],
        ];

        foreach ($mappings as $sourceType => [$categorySlug, $subcategorySlug, $modules]) {
            $categoryId = DB::table('categories')->where('slug', $categorySlug)->value('id');
            $subcategoryId = $subcategorySlug
                ? DB::table('subcategories')->where('category_id', $categoryId)->where('slug', $subcategorySlug)->value('id')
                : null;

            if (! $categoryId) {
                continue;
            }

            $this->upsertPreservingCreatedAt(
                'source_taxonomy_mappings',
                ['provider' => 'google_places', 'source_type' => $sourceType],
                [
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'recommended_modules' => json_encode($modules),
                    'confidence' => 1,
                    'is_active' => true,
                ],
                now(),
            );
        }
    }

    private function upsertPreservingCreatedAt(string $table, array $key, array $values, $timestamp): void
    {
        $query = DB::table($table)->where($key);

        if ($query->exists()) {
            $query->update([...$values, 'updated_at' => $timestamp]);

            return;
        }

        DB::table($table)->insert([...$key, ...$values, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
    }
};
