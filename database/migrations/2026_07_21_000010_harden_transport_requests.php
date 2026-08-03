<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('service_mode', 20)->default('taxi')->after('type');
            $table->decimal('capacity_value', 10, 2)->nullable()->after('seats');
            $table->string('capacity_unit', 20)->default('seats')->after('capacity_value');
            $table->string('availability_status', 20)->default('available')->after('description');
            $table->timestamp('next_available_at')->nullable()->after('availability_status');
            $table->boolean('requires_quote')->default(false)->after('fare_per_km');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->string('client_reference', 64)->nullable()->unique()->after('id');
            $table->string('request_type', 20)->default('ride')->after('vehicle_id');
            $table->timestamp('scheduled_at')->nullable()->after('trip_time');
            $table->timestamp('return_at')->nullable()->after('scheduled_at');
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('return_at');
            $table->decimal('load_weight', 10, 2)->nullable()->after('seats_required');
            $table->text('load_description')->nullable()->after('load_weight');
            $table->string('fare_status', 20)->default('estimated')->after('fare');
            $table->text('quote_notes')->nullable()->after('fare_status');
        });

        DB::table('trips')->update([
            'payment_status' => 'pending',
            'payment_method' => 'cash',
        ]);
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique(['client_reference']);
            $table->dropColumn([
                'client_reference',
                'request_type',
                'scheduled_at',
                'return_at',
                'estimated_duration_minutes',
                'load_weight',
                'load_description',
                'fare_status',
                'quote_notes',
            ]);
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'service_mode',
                'capacity_value',
                'capacity_unit',
                'availability_status',
                'next_available_at',
                'requires_quote',
            ]);
        });
    }
};
