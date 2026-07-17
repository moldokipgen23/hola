<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('district')->default('Churachandpur');
            $table->string('state')->default('Manipur');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('bounds_north', 10, 7)->nullable();
            $table->decimal('bounds_south', 10, 7)->nullable();
            $table->decimal('bounds_east', 10, 7)->nullable();
            $table->decimal('bounds_west', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('business_count')->default(0);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['district', 'is_active']);
        });

        // Seed Churachandpur areas
        $areas = [
            ['name' => 'Lamka', 'slug' => 'lamka', 'lat' => 24.3350, 'lng' => 93.7050, 'n' => 24.3500, 's' => 24.3200, 'e' => 93.7200, 'w' => 93.6900, 'order' => 1],
            ['name' => 'Tuibong', 'slug' => 'tuibong', 'lat' => 24.3100, 'lng' => 93.6900, 'n' => 24.3200, 's' => 24.3000, 'e' => 93.7000, 'w' => 93.6800, 'order' => 2],
            ['name' => 'New Lamka', 'slug' => 'new-lamka', 'lat' => 24.3450, 'lng' => 93.7150, 'n' => 24.3550, 's' => 24.3350, 'e' => 93.7250, 'w' => 93.7050, 'order' => 3],
            ['name' => 'Hiangtam Lamka', 'slug' => 'hiangtam-lamka', 'lat' => 24.3550, 'lng' => 93.7200, 'n' => 24.3650, 's' => 24.3450, 'e' => 93.7300, 'w' => 93.7100, 'order' => 4],
            ['name' => 'Vengnuam', 'slug' => 'vengnuam', 'lat' => 24.3200, 'lng' => 93.7000, 'n' => 24.3300, 's' => 24.3100, 'e' => 93.7100, 'w' => 93.6900, 'order' => 5],
            ['name' => 'Ramva', 'slug' => 'ramva', 'lat' => 24.3600, 'lng' => 93.6800, 'n' => 24.3700, 's' => 24.3500, 'e' => 93.6900, 'w' => 93.6700, 'order' => 6],
            ['name' => 'Saitual', 'slug' => 'saitual', 'lat' => 24.3700, 'lng' => 93.6600, 'n' => 24.3800, 's' => 24.3600, 'e' => 93.6700, 'w' => 93.6500, 'order' => 7],
            ['name' => 'Khawzawl', 'slug' => 'khawzawl', 'lat' => 24.3000, 'lng' => 93.6500, 'n' => 24.3100, 's' => 24.2900, 'e' => 93.6600, 'w' => 93.6400, 'order' => 8],
            ['name' => 'Churachandpur Town', 'slug' => 'churachandpur-town', 'lat' => 24.3300, 'lng' => 93.7000, 'n' => 24.3400, 's' => 24.3200, 'e' => 93.7100, 'w' => 93.6900, 'order' => 9],
            ['name' => 'Other', 'slug' => 'other', 'lat' => null, 'lng' => null, 'n' => null, 's' => null, 'e' => null, 'w' => null, 'order' => 99],
        ];

        foreach ($areas as $area) {
            DB::table('areas')->insert([
                'name' => $area['name'],
                'slug' => $area['slug'],
                'district' => 'Churachandpur',
                'state' => 'Manipur',
                'latitude' => $area['lat'],
                'longitude' => $area['lng'],
                'bounds_north' => $area['n'],
                'bounds_south' => $area['s'],
                'bounds_east' => $area['e'],
                'bounds_west' => $area['w'],
                'is_active' => true,
                'business_count' => 0,
                'order' => $area['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
