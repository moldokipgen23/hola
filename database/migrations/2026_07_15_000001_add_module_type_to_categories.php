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
            $table->string('module_type', 20)->default('directory')->after('is_active');
        });

        DB::table('categories')->whereIn('name', [
            'Food & Restaurants', 'Shopping & Retail', 'Electronics & Tech',
        ])->update(['module_type' => 'ordering']);

        DB::table('categories')->whereIn('name', [
            'Hotels & Lodges', 'Healthcare', 'Education', 'Beauty & Wellness',
            'Sports & Fitness', 'Automobiles', 'Preschool', 'Music School', 'Dance School',
        ])->update(['module_type' => 'booking']);

        DB::table('categories')->whereIn('name', [
            'Professional Services', 'Establishment', 'General',
        ])->update(['module_type' => 'directory']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('module_type');
        });
    }
};
