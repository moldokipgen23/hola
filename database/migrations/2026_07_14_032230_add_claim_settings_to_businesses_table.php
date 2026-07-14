<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('claim_notifications_enabled')->default(true)->after('claim_status');
            $table->unsignedTinyInteger('claim_notification_delay_days')->default(3)->after('claim_notifications_enabled');
            $table->string('claim_preferred_channel', 20)->default('all')->after('claim_notification_delay_days');
            $table->boolean('claim_auto_approve')->default(false)->after('claim_preferred_channel');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'claim_notifications_enabled',
                'claim_notification_delay_days',
                'claim_preferred_channel',
                'claim_auto_approve',
            ]);
        });
    }
};