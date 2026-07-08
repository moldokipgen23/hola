<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
