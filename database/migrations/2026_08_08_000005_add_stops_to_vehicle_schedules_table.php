<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boarding / drop-off stops for a schedule, giving a real bus-app pickup
 * experience. JSON shape (nullable — falls back to origin/destination):
 *
 *   "boarding_stops": [
 *     { "name": "Lamka Main Bus Stand", "time": "07:00", "price_offset": 0 },
 *     { "name": "New Lamka", "time": "07:20", "price_offset": 10 }
 *   ],
 *   "drop_stops": [
 *     { "name": "Aizawl Central", "time": "12:30", "price_offset": 0 }
 *   ]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->json('boarding_stops')->nullable()->after('notes');
            $table->json('drop_stops')->nullable()->after('boarding_stops');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_schedules', function (Blueprint $table) {
            $table->dropColumn(['boarding_stops', 'drop_stops']);
        });
    }
};
