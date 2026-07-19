<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type')->index();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });

        Schema::create('integration_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('integration_webhooks')->cascadeOnDelete();
            $table->string('event');
            $table->longText('payload');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_deliveries');
        Schema::dropIfExists('integration_webhooks');
    }
};
