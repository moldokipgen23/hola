<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('service_type')->default('directory')->after('area_id');
            $table->boolean('is_bookable')->default(false)->after('service_type');
            $table->integer('price_range')->nullable()->after('is_bookable');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'is_bookable', 'price_range']);
        });
    }
};
