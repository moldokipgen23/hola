<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'client_reference')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('client_reference', 64)->nullable()->after('id');
            });
        }

        $hasReferenceIndex = collect(Schema::getIndexes('bookings'))->contains(
            fn (array $index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['client_reference'],
        );
        if (! $hasReferenceIndex) {
            Schema::table('bookings', fn (Blueprint $table) => $table->unique('client_reference'));
        }

        if (! Schema::hasColumn('bookings', 'party_size')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('party_size')->default(1)->after('duration_minutes');
            });
        }

        if (! Schema::hasColumn('bookings', 'payment_status')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('payment_status', 20)->default('pending')->after('total_price');
            });
        }

        if (! Schema::hasColumn('bookings', 'payment_method')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('payment_method', 20)->default('cash')->after('payment_status');
            });
        }

        DB::table('bookings')->update([
            'payment_status' => 'pending',
            'payment_method' => 'cash',
        ]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['client_reference']);
            $table->dropColumn(['client_reference', 'party_size', 'payment_status', 'payment_method']);
        });
    }
};
