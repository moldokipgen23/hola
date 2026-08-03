<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('profile_complete')->default(false);
            $table->boolean('products_added')->default(false);
            $table->boolean('photos_uploaded')->default(false);
            $table->boolean('operating_hours_set')->default(false);
            $table->boolean('delivery_configured')->default(false);
            $table->boolean('first_order_received')->default(false);
            $table->boolean('first_booking_received')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_setups');
    }
};
