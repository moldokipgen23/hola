<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_interests', function (Blueprint $table) {
            $table->id();
            $table->string('pincode', 6)->nullable()->index();
            $table->string('locality')->nullable();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_interests');
    }
};
