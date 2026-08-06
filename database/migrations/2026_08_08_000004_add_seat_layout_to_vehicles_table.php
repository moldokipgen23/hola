<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visual seat layout for a vehicle, used for RedBus / aeroplane-style seat
 * selection. JSON shape:
 *
 *   [
 *     { "label": "A1", "row": 1, "col": 1, "deck": "lower", "type": "window" },
 *     { "label": "A2", "row": 1, "col": 2, "deck": "lower", "type": "aisle" },
 *     ...
 *   ]
 *
 * Nullable: when absent, the vendor just sets a seat count and the app falls
 * back to a simple numeric seat picker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('seat_layout')->nullable()->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('seat_layout');
        });
    }
};
