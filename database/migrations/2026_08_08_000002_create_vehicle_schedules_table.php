<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A concrete departure: one vendor vehicle running a route on a date/time.
 * Seats available = seats_capacity minus confirmed/pending schedule bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin');
            $table->string('destination');
            $table->date('departure_date');
            $table->time('departure_time');
            $table->unsignedInteger('seats_capacity');
            $table->decimal('price', 10, 2);
            $table->string('status')->default('scheduled'); // scheduled | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['origin', 'destination', 'departure_date']);
            $table->index(['business_id', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_schedules');
    }
};
