<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_audit_logs');
    }
};
