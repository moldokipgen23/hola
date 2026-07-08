<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->string('type')->comment('Skill name used, e.g. ai_business_scraper');
            $table->json('input')->comment('Task parameters');
            $table->json('output')->nullable()->comment('Raw AI response');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('result_count')->default(0);
            $table->integer('imported_count')->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->integer('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tasks');
    }
};
