<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-set terms & conditions for a vehicle, shown on the rental/hire
 * booking page and confirmed by the customer (e.g. security deposit, fuel,
 * driver, km limits, late return). Optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->text('terms')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('terms');
        });
    }
};
