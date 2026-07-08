<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('avatar')->nullable()->comment('Emoji or image path');
            $table->string('role')->comment('e.g. Business Discovery, Quality Control');
            $table->text('description')->nullable();
            $table->enum('provider', ['openrouter', 'openai', 'deepseek', 'anthropic'])->default('openrouter');
            $table->string('api_key')->nullable()->comment('Per-agent API key, falls back to global');
            $table->string('model')->default('deepseek/deepseek-chat')->comment('AI model to use');
            $table->text('system_prompt')->nullable()->comment('Custom instructions for the agent');
            $table->json('skills')->default('[]')->comment('List of skill names');
            $table->json('config')->default('{}')->comment('Skill-specific configuration');
            $table->enum('status', ['active', 'paused', 'error'])->default('active');
            $table->integer('tasks_completed')->default(0);
            $table->integer('tasks_failed')->default(0);
            $table->decimal('total_cost', 10, 4)->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
