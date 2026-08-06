<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the customer confirmed the owner's terms & conditions when booking
 * a vehicle hire / rental.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_rentals', function (Blueprint $table) {
            $table->boolean('terms_accepted')->default(false)->after('with_driver');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_rentals', function (Blueprint $table) {
            $table->dropColumn('terms_accepted');
        });
    }
};
