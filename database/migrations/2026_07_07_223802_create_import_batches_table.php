<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('source')->comment('google_places, ai_scrape, csv, manual');
            $table->string('name')->nullable()->comment('Batch name/description');
            $table->integer('total')->default(0);
            $table->integer('imported')->default(0);
            $table->integer('approved')->default(0);
            $table->integer('rejected')->default(0);
            $table->integer('skipped')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('config')->nullable()->comment('Import settings');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
