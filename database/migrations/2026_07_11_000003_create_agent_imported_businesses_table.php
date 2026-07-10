<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_imported_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('google_place_id')->nullable();
            $table->string('business_name');
            $table->string('address')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(['agent_id', 'business_id']);
            $table->index('google_place_id');
            $table->index(['agent_id', 'google_place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_imported_businesses');
    }
};
