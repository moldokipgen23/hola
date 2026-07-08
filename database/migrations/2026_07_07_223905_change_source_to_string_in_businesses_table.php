<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores enum as varchar already, so the raw MySQL ALTER is only
        // needed (and only valid) on MySQL.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE businesses MODIFY COLUMN source VARCHAR(50) DEFAULT 'manual'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE businesses MODIFY COLUMN source ENUM('admin','vendor','import') DEFAULT 'admin'");
        }
    }
};
