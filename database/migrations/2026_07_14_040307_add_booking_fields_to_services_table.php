<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('capacity')->nullable()->after('duration');
            $table->unsignedInteger('advance_booking_days')->nullable()->after('capacity');
            $table->unsignedInteger('cancellation_hours')->nullable()->after('advance_booking_days');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'advance_booking_days', 'cancellation_hours']);
        });
    }
};
