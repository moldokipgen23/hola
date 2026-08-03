<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_pincode', 6)->nullable()->after('delivery_address');
            $table->decimal('customer_latitude', 10, 7)->nullable()->after('delivery_pincode');
            $table->decimal('customer_longitude', 10, 7)->nullable()->after('customer_latitude');
            $table->string('client_reference', 64)->nullable()->unique()->after('order_number');
            $table->timestamp('inventory_released_at')->nullable()->after('cancelled_at');
        });

        $settings = [
            'payment_online_enabled' => '0',
            'payment_razorpay_enabled' => '0',
            'payment_cashfree_enabled' => '0',
            'payment_cod_enabled' => '1',
            'payment_default' => 'cod',
        ];

        foreach ($settings as $key => $value) {
            $existing = DB::table('settings')->where('key', $key)->exists();
            DB::table('settings')->updateOrInsert(['key' => $key], array_filter([
                'value' => $value,
                'group' => 'payments',
                'updated_at' => now(),
                'created_at' => $existing ? null : now(),
            ], fn ($field) => $field !== null));
        }

        DB::table('orders')->whereNull('payment_method')->update(['payment_method' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['client_reference']);
            $table->dropColumn([
                'delivery_pincode',
                'customer_latitude',
                'customer_longitude',
                'client_reference',
                'inventory_released_at',
            ]);
        });
    }
};
