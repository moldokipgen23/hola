<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('world')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('clicked_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->unsignedTinyInteger('clicked_position')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['query', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};
