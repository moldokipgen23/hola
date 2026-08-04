<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Shop sections — the platform-defined storefront sections
 * (Grocery, Food, Medicine, Shopping). Completely independent from the
 * Directory business-classification taxonomy in the `categories` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sections', function (Blueprint $table) {
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
            ['Grocery', 'grocery', 'Vegetables, fruits, meat, fish and daily essentials.'],
            ['Food', 'food', 'Starters, main course, drinks and desserts.'],
            ['Medicine', 'medicine', 'Pharmacy and healthcare products.'],
            ['Shopping', 'shopping', 'Retail and general merchandise.'],
        ];

        foreach ($defaults as $order => [$name, $slug, $description]) {
            DB::table('shop_sections')->insert([
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
        Schema::dropIfExists('shop_sections');
    }
};
