<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('menu_section', 100)->nullable()->after('description');
            $table->string('food_type', 20)->nullable()->after('menu_section');
            $table->unsignedSmallInteger('preparation_minutes')->nullable()->after('food_type');
            $table->time('available_from')->nullable()->after('preparation_minutes');
            $table->time('available_until')->nullable()->after('available_from');
            $table->timestamp('sold_out_until')->nullable()->after('available_until');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('estimated_ready_at')->nullable()->after('delivery_time_slot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('estimated_ready_at'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'menu_section',
                'food_type',
                'preparation_minutes',
                'available_from',
                'available_until',
                'sold_out_until',
            ]);
        });
    }
};
