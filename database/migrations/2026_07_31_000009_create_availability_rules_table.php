<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('bookable_resources')->cascadeOnDelete();
            $table->string('day_of_week')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('slot_duration_minutes')->nullable();
            $table->integer('buffer_minutes')->default(0);
            $table->integer('capacity')->nullable();
            $table->json('blackout_dates')->nullable();
            $table->integer('booking_window_days')->default(30);
            $table->integer('minimum_notice_hours')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
