<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sports & Fitness (id=10): Add Football Turf, Swimming Pool, Picnic Spot
        $sportsId = 10;
        $now = now()->toDateTimeString();

        $subcategories = [
            // Under Sports & Fitness (10)
            ['category_id' => $sportsId, 'name' => 'Football Turf', 'slug' => 'football-turf', 'icon' => '⚽', 'order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $sportsId, 'name' => 'Swimming Pool', 'slug' => 'swimming-pool', 'icon' => '🏊', 'order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $sportsId, 'name' => 'Picnic Spot', 'slug' => 'picnic-spot', 'icon' => '🏞️', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => $sportsId, 'name' => 'Amusement Park', 'slug' => 'amusement-park', 'icon' => '🎡', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Hotels & Lodges (2): Add Resorts
            ['category_id' => 2, 'name' => 'Resorts', 'slug' => 'resorts', 'icon' => '🏖️', 'order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Shopping & Retail (5): Add Shopping Mall, Electronics Store
            ['category_id' => 5, 'name' => 'Shopping Mall', 'slug' => 'shopping-mall', 'icon' => '🏬', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['category_id' => 5, 'name' => 'Electronics Store', 'slug' => 'electronics-store', 'icon' => '📱', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Healthcare (3): Add Diagnostic Lab
            ['category_id' => 3, 'name' => 'Diagnostic Lab', 'slug' => 'diagnostic-lab', 'icon' => '🔬', 'order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],

            // Under Food & Restaurants (1): Add Catering
            ['category_id' => 1, 'name' => 'Catering', 'slug' => 'catering', 'icon' => '🍱', 'order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
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
