<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pincodes', function (Blueprint $table) {
            $table->boolean('serviceable')->default(false)->change();
        });

        DB::table('pincodes')->update(['serviceable' => false]);
        DB::table('pincodes')
            ->whereRaw('LOWER(state) = ?', ['manipur'])
            ->whereRaw('LOWER(district) = ?', ['churachandpur'])
            ->update(['serviceable' => true]);
    }

    public function down(): void
    {
        Schema::table('pincodes', function (Blueprint $table) {
            $table->boolean('serviceable')->default(true)->change();
        });

        DB::table('pincodes')->update(['serviceable' => true]);
    }
};
