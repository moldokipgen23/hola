<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('availability_updated_at')->nullable()->after('experience_config');
            $table->boolean('availability_is_stale')->default(false)->after('availability_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['availability_updated_at', 'availability_is_stale']);
        });
    }
};