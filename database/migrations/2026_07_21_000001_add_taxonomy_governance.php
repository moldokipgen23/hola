<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_canonical')->default(false)->after('module_type')->index();
        });

        Schema::table('subcategories', function (Blueprint $table) {
            $table->json('recommended_modules')->nullable()->after('is_active');
        });

        DB::table('categories')->whereIn('slug', [
            'food-restaurants',
            'hotels-lodges',
            'healthcare',
            'education',
            'shopping-retail',
            'electronics-tech',
            'automobiles',
            'beauty-wellness',
            'professional-services',
            'sports-fitness',
        ])->update(['is_canonical' => true]);
    }

    public function down(): void
    {
        Schema::table('subcategories', function (Blueprint $table) {
            $table->dropColumn('recommended_modules');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_canonical']);
            $table->dropColumn('is_canonical');
        });
    }
};
