<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('source');
            $table->integer('import_batch_id')->nullable()->after('external_id');
            $table->decimal('confidence', 3, 2)->nullable()->after('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'import_batch_id', 'confidence']);
        });
    }
};
