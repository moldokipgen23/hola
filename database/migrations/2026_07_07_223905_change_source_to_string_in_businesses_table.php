<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE businesses MODIFY COLUMN source VARCHAR(50) DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE businesses MODIFY COLUMN source ENUM('admin','vendor','import') DEFAULT 'admin'");
    }
};
