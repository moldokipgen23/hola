<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seat reservation against a vehicle_schedule. One row per booking; stores
 * the selected seat labels (e.g. ["A1","A2"]) and the fare charged.
 *
 * Kept separate from `trips` (on-demand taxi/cargo requests) and from the
 * shopping `orders` / general `bookings` tables so transport seat bookings
 * stay self-contained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->json('seat_labels')->nullable();
            $table->unsignedInteger('seats');
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending | confirmed | cancelled | completed | no_show
            $table->string('payment_status')->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('client_reference')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_bookings');
    }
};
