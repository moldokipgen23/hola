<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('otp', 6);
            $table->string('channel', 10); // whatsapp, email
            $table->boolean('verified')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['business_id', 'verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_verifications');
    }
};
