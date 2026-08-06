<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated transport routes (e.g. Lamka → Aizawl). Vendors can pick a
 * route when creating a schedule, or define their own route inline. This is
 * the shared reference for origin/destination/distance so seat bookings can
 * be grouped and searched consistently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->string('origin');
            $table->string('destination');
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('base_fare', 10, 2)->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['origin', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_routes');
    }
};
