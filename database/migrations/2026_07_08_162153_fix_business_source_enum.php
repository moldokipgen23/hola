<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change source from enum to string to support google_places, ai_scrape, csv, etc.
        // SQLite stores enum as varchar already, so the raw MySQL ALTER is only needed on MySQL.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE businesses MODIFY COLUMN source VARCHAR(50) DEFAULT 'admin'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE businesses MODIFY COLUMN source ENUM('admin', 'vendor', 'import') DEFAULT 'admin'");
        }
    }
};
