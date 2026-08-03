<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('primary_experience')->nullable()->after('service_type');
            $table->json('enabled_experiences')->nullable()->after('primary_experience');
            $table->json('experience_config')->nullable()->after('enabled_experiences');
            $table->index('primary_experience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['primary_experience']);
            $table->dropColumn(['primary_experience', 'enabled_experiences', 'experience_config']);
        });
    }
};