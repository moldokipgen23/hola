<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-set distance and travel time per departure. The admin route only
 * carries optional hints; the vendor controls the real values on each
 * schedule so pricing and duration are competitive and accurate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->decimal('distance_km', 10, 2)->nullable()->after('destination');
            $table->unsignedInteger('estimated_minutes')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->dropColumn(['distance_km', 'estimated_minutes']);
        });
    }
};
