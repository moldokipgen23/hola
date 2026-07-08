<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->json('data')->comment('Raw business data from import source');
            $table->enum('status', ['pending', 'approved', 'rejected', 'duplicate'])->default('pending');
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete()->comment('Linked after approval');
            $table->string('external_id')->nullable()->comment('Google place_id or source ID');
            $table->text('notes')->nullable()->comment('Admin notes or rejection reason');
            $table->decimal('confidence', 3, 2)->nullable()->comment('AI confidence score 0.00-1.00');
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index('external_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_items');
    }
};
