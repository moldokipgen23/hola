<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->enum('status', ['pending', 'categorized', 'review', 'merged', 'approved', 'rejected', 'duplicate'])
                ->default('pending')
                ->change();

            // Canonical business an item was merged into (duplicate merge).
            $table->unsignedBigInteger('duplicate_of')->nullable()->after('business_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->dropColumn(['duplicate_of']);

            $table->enum('status', ['pending', 'approved', 'rejected', 'duplicate'])
                ->default('pending')
                ->change();
        });
    }
};
