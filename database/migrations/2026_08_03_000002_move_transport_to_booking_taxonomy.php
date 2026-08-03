<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the existing slug: import mappings and any future records still use it.
        DB::table('categories')->where('slug', 'transport')->update([
            'name' => 'Taxi & Transport',
            'module_type' => 'booking',
            'icon' => '🚕',
            'updated_at' => now(),
        ]);

        DB::table('subcategories')->where('slug', 'taxi-services')->update([
            'name' => 'Taxi',
            'recommended_modules' => json_encode(['transport']),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'transport')->update([
            'name' => 'Transport',
            'module_type' => 'directory',
            'updated_at' => now(),
        ]);
    }
};
