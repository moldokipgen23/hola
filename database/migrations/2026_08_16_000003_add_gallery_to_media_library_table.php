<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('category');
            $table->boolean('is_cover')->default(false)->after('sort_order');
            $table->string('status')->default('approved')->after('is_cover');
            $table->text('moderation_reason')->nullable()->after('status');
            $table->timestamp('flagged_at')->nullable()->after('moderation_reason');

            $table->index(['business_id', 'is_cover']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'is_cover']);
            $table->dropIndex(['business_id', 'status']);
            $table->dropColumn(['sort_order', 'is_cover', 'status', 'moderation_reason', 'flagged_at']);
        });
    }
};
