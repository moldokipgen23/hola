<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->string('query');
            $table->string('area')->nullable();
            $table->string('zipcode')->nullable();
            $table->string('source'); // google_places, serpapi
            $table->integer('total_found')->default(0);
            $table->integer('new_places')->default(0);
            $table->integer('already_imported')->default(0);
            $table->integer('duplicates')->default(0);
            $table->json('place_ids')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_history');
    }
};
