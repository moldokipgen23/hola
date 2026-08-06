<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('preparing_at')->nullable()->after('confirmed_at');
            $table->timestamp('out_for_delivery_at')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['preparing_at', 'out_for_delivery_at']);
        });
    }
};
