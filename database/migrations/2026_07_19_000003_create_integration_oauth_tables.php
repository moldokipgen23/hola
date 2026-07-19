<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type')->index();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('client_id', 80)->unique();
            $table->string('client_secret', 120)->nullable();
            $table->json('redirect_uris');
            $table->json('grants');
            $table->json('scopes');
            $table->boolean('is_confidential')->default(true);
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });

        Schema::create('integration_oauth_authorization_codes', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->foreignId('client_id')->constrained('integration_oauth_clients')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('scopes');
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('integration_oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->foreignId('client_id')->constrained('integration_oauth_clients')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('scopes');
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('integration_oauth_refresh_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->string('access_token_id', 100)->index();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_oauth_refresh_tokens');
        Schema::dropIfExists('integration_oauth_access_tokens');
        Schema::dropIfExists('integration_oauth_authorization_codes');
        Schema::dropIfExists('integration_oauth_clients');
    }
};
