<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.5 — Backfill a primary classification for every business that does
 * not have one, derived from businesses.category_id (the admin form + API now
 * maintain it on every create/update via Business::syncPrimaryClassification).
 * Idempotent and non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $businesses = DB::table('businesses')
            ->whereNull('deleted_at')
            ->whereNotNull('category_id')
            ->get(['id', 'category_id']);

        $now = now()->toDateTimeString();

        foreach ($businesses as $business) {
            $hasPrimary = DB::table('business_classifications')
                ->where('business_id', $business->id)
                ->where('is_primary', true)
                ->exists();

            if ($hasPrimary) {
                continue;
            }

            $existsForCategory = DB::table('business_classifications')
                ->where('business_id', $business->id)
                ->where('category_id', $business->category_id)
                ->exists();

            if ($existsForCategory) {
                DB::table('business_classifications')
                    ->where('business_id', $business->id)
                    ->where('category_id', $business->category_id)
                    ->update(['is_primary' => true]);

                continue;
            }

            DB::table('business_classifications')->insert([
                'business_id' => $business->id,
                'category_id' => $business->category_id,
                'is_primary' => true,
                'is_active' => true,
                'source' => 'system_migrated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('business_classifications')
            ->where('source', 'system_migrated')
            ->where('is_primary', true)
            ->delete();
    }
};
