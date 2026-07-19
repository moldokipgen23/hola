<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 8);
            $table->json('scopes');
            $table->string('tenant_type')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_api_keys');
    }
};
