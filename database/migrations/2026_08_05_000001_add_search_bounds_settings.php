<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bounds = [
            'north' => '24.4500',
            'south' => '24.2000',
            'east' => '93.8500',
            'west' => '93.5500',
        ];

        foreach ($bounds as $dir => $value) {
            DB::table('settings')->insert([
                'key' => "search_bounds_{$dir}",
                'value' => $value,
                'group' => 'search',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', ['search_bounds_north', 'search_bounds_south', 'search_bounds_east', 'search_bounds_west'])
            ->delete();
    }
};
