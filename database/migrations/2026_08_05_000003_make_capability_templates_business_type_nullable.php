<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capability_templates', function (Blueprint $table) {
            $table->string('business_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('capability_templates', function (Blueprint $table) {
            $table->string('business_type')->nullable(false)->change();
        });
    }
};
