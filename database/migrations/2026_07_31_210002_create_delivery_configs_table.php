<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->decimal('delivery_radius_km', 8, 2)->default(5.00);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('free_delivery_above', 10, 2)->nullable();
            $table->integer('estimated_time_minutes')->default(30);
            $table->json('zones')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_configs');
    }
};
