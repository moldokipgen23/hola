<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('booking_mode', 20)->default('appointment')->after('has_fixed_slots');
            $table->unsignedInteger('inventory_units')->default(1)->after('capacity');
            $table->string('unit_label', 40)->nullable()->after('inventory_units');
            $table->string('price_unit', 20)->default('booking')->after('price');
            $table->string('check_in_time', 5)->nullable()->after('price_unit');
            $table->string('check_out_time', 5)->nullable()->after('check_in_time');
            $table->unsignedInteger('min_stay_nights')->default(1)->after('check_out_time');
            $table->unsignedInteger('max_stay_nights')->nullable()->after('min_stay_nights');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('check_in_date')->nullable()->after('booking_date');
            $table->date('check_out_date')->nullable()->after('check_in_date');
            $table->unsignedInteger('reservation_units')->default(1)->after('party_size');
            $table->json('seat_labels')->nullable()->after('reservation_units');
            $table->decimal('unit_price', 10, 2)->default(0)->after('seat_labels');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['check_in_date', 'check_out_date', 'reservation_units', 'seat_labels', 'unit_price']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'booking_mode', 'inventory_units', 'unit_label', 'price_unit',
                'check_in_time', 'check_out_time', 'min_stay_nights', 'max_stay_nights',
            ]);
        });
    }
};
