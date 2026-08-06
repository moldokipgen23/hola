<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('comment');
            $table->string('moderation_reason')->nullable()->after('status');
            $table->timestamp('flagged_at')->nullable()->after('moderation_reason');
            $table->string('photo')->nullable()->after('flagged_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'moderation_reason', 'flagged_at', 'photo']);
        });
    }
};
