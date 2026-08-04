<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link per-business product categories to a global Shop section.
 * The `product_categories` table stays independent from the Directory
 * taxonomy; the section only groups them inside a business storefront.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('shop_section_id')
                ->nullable()
                ->after('business_id')
                ->constrained('shop_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['shop_section_id']);
            $table->dropColumn('shop_section_id');
        });
    }
};
