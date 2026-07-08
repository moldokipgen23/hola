<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->integer('call_count')->default(0);
            $table->integer('whatsapp_count')->default(0);
            $table->integer('directions_count')->default(0);
            $table->integer('share_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['call_count', 'whatsapp_count', 'directions_count', 'share_count']);
        });
    }
};
