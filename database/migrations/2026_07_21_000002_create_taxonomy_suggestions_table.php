<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('import_item_id')->nullable()->constrained('import_items')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('suggestion_type', 30);
            $table->string('suggested_name');
            $table->foreignId('suggested_parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('source_provider', 50)->nullable();
            $table->string('source_type')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['suggestion_type', 'suggested_name']);
            $table->index(['source_provider', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_suggestions');
    }
};
