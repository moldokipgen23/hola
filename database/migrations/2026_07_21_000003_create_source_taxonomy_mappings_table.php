<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_taxonomy_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('source_type');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->nullOnDelete();
            $table->json('recommended_modules')->nullable();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['provider', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_taxonomy_mappings');
    }
};
