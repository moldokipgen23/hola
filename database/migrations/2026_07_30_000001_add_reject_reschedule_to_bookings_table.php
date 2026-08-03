<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 20)->change();
            $table->text('rejection_reason')->nullable()->after('cancellation_reason');
            $table->timestamp('rejected_at')->nullable()->after('cancelled_at');
            $table->timestamp('rescheduled_at')->nullable()->after('rejected_at');
            $table->date('rescheduled_to_date')->nullable()->after('rescheduled_at');
            $table->time('rescheduled_to_time')->nullable()->after('rescheduled_to_date');
            $table->text('reschedule_reason')->nullable()->after('rescheduled_to_time');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'rejection_reason',
                'rejected_at',
                'rescheduled_at',
                'rescheduled_to_date',
                'rescheduled_to_time',
                'reschedule_reason',
            ]);
        });
    }
};
