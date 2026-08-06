<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily hire rate for a vehicle, used for per-date rental bookings
 * (excursion / self-drive / hire with driver). Nullable — only relevant for
 * rental-service vehicles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('price_per_day', 10, 2)->nullable()->after('fare_per_km');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('price_per_day');
        });
    }
};
