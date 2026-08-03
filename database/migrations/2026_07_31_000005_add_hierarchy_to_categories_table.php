<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('world_id')->nullable()->after('id')->constrained('worlds')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('world_id')->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable()->after('image');
            $table->integer('level')->default(0)->after('description');
            $table->boolean('show_on_home')->default(false)->after('level');
            $table->string('launch_phase')->default('phase1')->after('show_on_home');
            $table->foreignId('capability_template_id')->nullable()->after('launch_phase')->constrained('capability_templates')->nullOnDelete();
            $table->json('metadata')->nullable()->after('capability_template_id');
            $table->string('business_type')->nullable()->after('module_type');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['world_id']);
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['capability_template_id']);
            $table->dropColumn([
                'world_id', 'parent_id', 'description', 'level',
                'show_on_home', 'launch_phase', 'capability_template_id',
                'metadata', 'business_type',
            ]);
        });
    }
};
