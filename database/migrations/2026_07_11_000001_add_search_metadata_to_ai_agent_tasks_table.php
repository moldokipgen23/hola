<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            $table->json('search_metadata')->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            $table->dropColumn('search_metadata');
        });
    }
};
