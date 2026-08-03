<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('business_type');
            $table->text('description')->nullable();
            $table->json('enabled_modules')->nullable()->default(null);
            $table->json('enabled_experiences')->nullable()->default(null);
            $table->json('default_availability')->nullable()->default(null);
            $table->json('fulfilment_options')->nullable()->default(null);
            $table->json('required_fields')->nullable()->default(null);
            $table->json('allowed_filters')->nullable()->default(null);
            $table->json('vendor_menu_config')->nullable()->default(null);
            $table->json('customer_cta_config')->nullable()->default(null);
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_templates');
    }
};
