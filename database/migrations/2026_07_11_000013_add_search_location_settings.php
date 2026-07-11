<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'search_district', 'value' => 'Churachandpur', 'group' => 'search', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'search_state', 'value' => 'Manipur', 'group' => 'search', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'search_zipcodes', 'value' => '795128', 'group' => 'search', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'search_areas', 'value' => 'Lamka, New Lamka, Tuibong, Zou Road, Main Bazaar, Hmar Veng', 'group' => 'search', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($settings as $s) {
            $exists = DB::table('settings')->where('key', $s['key'])->exists();
            if (!$exists) {
                DB::table('settings')->insert($s);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'search_district', 'search_state', 'search_zipcodes', 'search_areas',
        ])->delete();
    }
};
