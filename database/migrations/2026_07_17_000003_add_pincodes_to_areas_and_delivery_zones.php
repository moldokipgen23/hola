<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->json('pincodes')->nullable()->after('bounds_west');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->json('pincodes')->nullable()->after('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('pincodes');
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn('pincodes');
        });
    }
};
