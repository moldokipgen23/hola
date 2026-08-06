<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-date vehicle hire / rental bookings (excursion, self-drive, hire with
 * driver). One row per hire; a vehicle is unavailable on any date that
 * overlaps a pending/confirmed hire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('price_per_day', 10, 2)->default(0);
            $table->integer('days');
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending | confirmed | completed | cancelled
            $table->string('payment_status')->default('pending');
            $table->boolean('with_driver')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
            $table->index(['business_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_rentals');
    }
};
