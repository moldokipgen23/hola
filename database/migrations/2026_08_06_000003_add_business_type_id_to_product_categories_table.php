<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin product categories are managed per shopping business type (a shop-world
 * root category) instead of per business. `business_id` becomes nullable for
 * backwards compatibility with vendor-created per-business categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->change();

            $table->unsignedBigInteger('business_type_id')
                ->nullable()
                ->after('business_id');
            $table->foreign('business_type_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
            $table->index('business_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn('business_type_id');
            $table->foreignId('business_id')->nullable(false)->change();
        });
    }
};
