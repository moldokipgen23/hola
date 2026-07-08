<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('import_batches', 'pending')) {
            Schema::table('import_batches', function (Blueprint $table) {
                $table->integer('pending')->default(0)->after('skipped');
            });
        }
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('pending');
        });
    }
};
