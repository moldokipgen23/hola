<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('group')->default('general');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_visible_to_customers')->default(false);
            $table->string('launch_phase')->nullable();
            $table->json('enabled_areas')->nullable();
            $table->json('enabled_businesses')->nullable();
            $table->json('enabled_categories')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
