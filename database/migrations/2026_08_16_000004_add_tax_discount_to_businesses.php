<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('tax_percent', 5, 2)->default(0)->after('payment_methods');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('tax_percent');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['tax_percent', 'discount_amount']);
        });
    }
};
