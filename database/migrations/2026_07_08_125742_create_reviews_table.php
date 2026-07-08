<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
