<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The workflow (OrderWorkflowService) supports 'rejected' but the
        // column enum never included it — saving a rejected order was a 500 on
        // MySQL strict mode. Add 'rejected' alongside the existing values.
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'ready',
                'out_for_delivery', 'delivered', 'cancelled', 'refunded', 'rejected',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'ready',
                'out_for_delivery', 'delivered', 'cancelled', 'refunded',
            ])->default('pending')->change();
        });
    }
};
