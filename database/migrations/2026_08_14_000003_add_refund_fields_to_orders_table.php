<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('rejected_at');
            $table->text('refund_reason')->nullable()->after('rejected_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // FK can't cleanly re-constrain in down without data guarantees.
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refund_reason']);
        });
    }
};
