<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global vehicle types for the Ride / Transport department
 * (car, truck, bus, etc). Vendors pick from these when adding a
 * transport option; the platform admin manages the list.
 * Stored as slug on vehicles.type for backward compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaults = [
            ['Car', 'car', 'Sedans, hatchbacks and hatchback-style private rides.'],
            ['Bolero', 'bolero', 'SUVs and Bolero-style people movers.'],
            ['SUV', 'suv', 'Large multi-utility vehicles.'],
            ['Van', 'van', 'Passenger vans and mini carriers.'],
            ['Auto', 'auto', 'Three-wheeler auto rickshaws.'],
            ['Bike', 'bike', 'Two-wheelers and bike taxis.'],
            ['Bus', 'bus', 'City and intercity buses.'],
            ['Truck', 'truck', 'Goods trucks and heavy carriers.'],
            ['Pickup', 'pickup', 'Pickup trucks for small cargo.'],
            ['Tempo', 'tempo', 'Tempo travellers and medium goods carriers.'],
        ];

        foreach ($defaults as $order => [$name, $slug, $description]) {
            DB::table('vehicle_types')->insert([
                'name' => $name,
                'slug' => $slug,
                'icon' => null,
                'description' => $description,
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
