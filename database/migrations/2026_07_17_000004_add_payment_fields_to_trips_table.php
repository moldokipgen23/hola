<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'payment_status')) {
                $table->string('payment_status', 20)->default('unpaid');
            }
            if (! Schema::hasColumn('trips', 'payment_method')) {
                $table->string('payment_method', 20)->nullable();
            }
            if (! Schema::hasColumn('trips', 'razorpay_order_id')) {
                $table->string('razorpay_order_id')->nullable();
            }
            if (! Schema::hasColumn('trips', 'razorpay_payment_id')) {
                $table->string('razorpay_payment_id')->nullable();
            }
            if (! Schema::hasColumn('trips', 'razorpay_signature')) {
                $table->string('razorpay_signature')->nullable();
            }
            if (! Schema::hasColumn('trips', 'cashfree_order_id')) {
                $table->string('cashfree_order_id')->nullable();
            }
            if (! Schema::hasColumn('trips', 'cashfree_payment_session_id')) {
                $table->string('cashfree_payment_session_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = ['payment_status', 'payment_method', 'razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'cashfree_order_id', 'cashfree_payment_session_id'];
        $existing = array_filter($columns, fn ($col) => Schema::hasColumn('trips', $col));

        if ($existing) {
            Schema::table('trips', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
