<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();

        // Map category slugs to their IDs
        $categories = DB::table('categories')
            ->whereIn('slug', ['food-restaurants', 'hotels-lodges', 'healthcare', 'shopping-retail', 'sports-fitness'])
            ->pluck('id', 'slug')
            ->toArray();

        // Skip if categories don't exist (migration running before seeders)
        if (empty($categories)) {
            return;
        }

        $subcategories = [
            // Under Sports & Fitness (sports-fitness)
            ['category_id' => $categories['sports-fitness'], 'name' => 'Football Turf', 'slug' => 'football-turf', 'icon' => '⚽', 'order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $categories['sports-fitness'], 'name' => 'Swimming Pool', 'slug' => 'swimming-pool', 'icon' => '🏊', 'order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $categories['sports-fitness'], 'name' => 'Picnic Spot', 'slug' => 'picnic-spot', 'icon' => '🏞️', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $categories['sports-fitness'], 'name' => 'Amusement Park', 'slug' => 'amusement-park', 'icon' => '🎡', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Hotels & Lodges (hotels-lodges): Add Resorts
            ['category_id' => $categories['hotels-lodges'], 'name' => 'Resorts', 'slug' => 'resorts', 'icon' => '🏖️', 'order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Shopping & Retail (shopping-retail): Add Shopping Mall, Electronics Store
            ['category_id' => $categories['shopping-retail'], 'name' => 'Shopping Mall', 'slug' => 'shopping-mall', 'icon' => '🏬', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $categories['shopping-retail'], 'name' => 'Electronics Store', 'slug' => 'electronics-store', 'icon' => '📱', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Healthcare (healthcare): Add Diagnostic Lab
            ['category_id' => $categories['healthcare'], 'name' => 'Diagnostic Lab', 'slug' => 'diagnostic-lab', 'icon' => '🔬', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Food & Restaurants (food-restaurants): Add Catering
            ['category_id' => $categories['food-restaurants'], 'name' => 'Catering', 'slug' => 'catering', 'icon' => '🍱', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('subcategories')->insert($subcategories);
    }

    public function down(): void
    {
        DB::table('subcategories')->whereIn('slug', [
            'football-turf', 'swimming-pool', 'picnic-spot', 'amusement-park',
            'resorts', 'shopping-mall', 'electronics-store', 'diagnostic-lab', 'catering',
        ])->delete();
    }
};