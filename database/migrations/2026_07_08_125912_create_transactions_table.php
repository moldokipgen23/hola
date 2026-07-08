<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->nullableMorphs('billable');
            $table->string('type')->default('featured_listing');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('INR');
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
