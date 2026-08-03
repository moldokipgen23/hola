<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Finalises the single-taxonomy backfill left incomplete by
 * 2026_07_31_000006 (which only set world_id on ROOT categories and left the
 * legacy `both` bucket in place). Idempotent and non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Retire the legacy `both` bucket — the category form no longer offers it.
        DB::table('categories')->where('module_type', 'both')->update(['module_type' => 'ordering']);

        // 2. Backfill world_id on any ROOT category still missing one.
        $worldIdBySlug = DB::table('worlds')->pluck('id', 'slug');
        $map = ['directory' => 'discover', 'ordering' => 'shop', 'booking' => 'book'];
        foreach ($map as $moduleType => $slug) {
            if (! isset($worldIdBySlug[$slug])) {
                continue;
            }
            DB::table('categories')
                ->where('module_type', $moduleType)
                ->whereNull('parent_id')
                ->whereNull('world_id')
                ->update(['world_id' => $worldIdBySlug[$slug]]);
        }

        // 3. Children inherit their parent's world (walk shallow→deep; a few passes
        //    resolves multi-level trees). Only touches rows still missing a world.
        for ($pass = 0; $pass < 6; $pass++) {
            $orphanChildren = DB::table('categories')
                ->whereNotNull('parent_id')
                ->whereNull('world_id')
                ->get(['id', 'parent_id']);

            if ($orphanChildren->isEmpty()) {
                break;
            }

            $changed = false;
            foreach ($orphanChildren as $child) {
                $parentWorld = DB::table('categories')->where('id', $child->parent_id)->value('world_id');
                if ($parentWorld) {
                    DB::table('categories')->where('id', $child->id)->update(['world_id' => $parentWorld]);
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        // 4. Normalise level: root = 1, direct children (unset/0) = 2.
        DB::table('categories')->whereNull('parent_id')->update(['level' => 1]);
        DB::table('categories')
            ->whereNotNull('parent_id')
            ->where(fn ($q) => $q->whereNull('level')->orWhere('level', 0))
            ->update(['level' => 2]);
    }

    public function down(): void
    {
        // Backfill only — nothing to reverse safely.
    }
};
