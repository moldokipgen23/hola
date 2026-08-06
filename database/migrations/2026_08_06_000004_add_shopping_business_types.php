<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Establishes the clean shopping business types (Grocery, Food, Medicine,
 * General Shopping) as shop-world root categories and migrates the legacy
 * shop taxonomy onto them.
 *
 * - Creates/promotes the four business-type roots under the shop world.
 * - Moves the legacy food subcategories (restaurants, street-food, bakeries,
 *   cafes, fast-food, catering) under Food; grocery-stores under Grocery;
 *   pharmacies under Medicine; and the remaining shop subcategories (clothing,
 *   hardware-stores, stationery, shopping-mall, electronics-store, mobile-shops,
 *   computer-stores, repair-shops) under General Shopping.
 * - Re-points businesses classified directly on the legacy shop roots.
 * - Deactivates the legacy roots (food-restaurants, shopping-retail,
 *   electronics-tech) and any other leftover shop-world roots so the admin
 *   tabs only show the four business types.
 *
 * Idempotent: re-running only fills in anything still missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $shopWorldId = DB::table('worlds')->where('slug', 'shop')->value('id');

        if (! $shopWorldId) {
            return;
        }

        $now = now();

        // 0. Reconcile the legacy "General" root (old WorldSeeder) to the
        //    canonical "General Shopping" business-type slug.
        if (! DB::table('categories')->where('slug', 'general-shopping')->exists()) {
            $legacyGeneral = DB::table('categories')
                ->where('slug', 'general')
                ->where('world_id', $shopWorldId)
                ->whereNull('parent_id')
                ->first();

            if ($legacyGeneral) {
                DB::table('categories')->where('id', $legacyGeneral->id)->update([
                    'slug' => 'general-shopping',
                    'name' => 'General Shopping',
                ]);
            }
        }

        // 1. Ensure the four business-type roots exist.
        $rootNames = [
            'grocery' => 'Grocery',
            'food' => 'Food',
            'medicine' => 'Medicine',
            'general-shopping' => 'General Shopping',
        ];

        $rootIds = [];
        foreach ($rootNames as $slug => $name) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');

            if ($id) {
                DB::table('categories')->where('id', $id)->update([
                    'name' => $name,
                    'world_id' => $shopWorldId,
                    'parent_id' => null,
                    'level' => 1,
                    'module_type' => 'ordering',
                    'is_active' => true,
                ]);
            } else {
                $id = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'module_type' => 'ordering',
                    'world_id' => $shopWorldId,
                    'parent_id' => null,
                    'level' => 1,
                    'is_active' => true,
                    'is_canonical' => false,
                    'launch_phase' => 'phase1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rootIds[$slug] = $id;
        }

        $catId = fn (string $slug) => DB::table('categories')->where('slug', $slug)->value('id');

        // 2. Move legacy shop subcategories under the right business type.
        $moves = [
            'restaurants' => 'food',
            'street-food' => 'food',
            'bakeries' => 'food',
            'cafes' => 'food',
            'fast-food' => 'food',
            'catering' => 'food',
            'grocery-stores' => 'grocery',
            'pharmacies' => 'medicine',
            'clothing' => 'general-shopping',
            'hardware-stores' => 'general-shopping',
            'stationery' => 'general-shopping',
            'shopping-mall' => 'general-shopping',
            'electronics-store' => 'general-shopping',
            'mobile-shops' => 'general-shopping',
            'computer-stores' => 'general-shopping',
            'repair-shops' => 'general-shopping',
        ];

        foreach ($moves as $childSlug => $rootSlug) {
            $childId = $catId($childSlug);
            $rootId = $rootIds[$rootSlug] ?? null;

            if ($childId && $rootId) {
                DB::table('categories')->where('id', $childId)->update([
                    'parent_id' => $rootId,
                    'world_id' => $shopWorldId,
                    'level' => 2,
                ]);
            }
        }

        // 3. Re-point businesses classified directly on the legacy shop roots.
        $legacyRootMap = [
            'food-restaurants' => 'food',
            'shopping-retail' => 'general-shopping',
            'electronics-tech' => 'general-shopping',
        ];

        foreach ($legacyRootMap as $legacySlug => $rootSlug) {
            $legacyId = $catId($legacySlug);
            $newId = $rootIds[$rootSlug] ?? null;

            if (! $legacyId || ! $newId) {
                continue;
            }

            DB::table('businesses')->where('category_id', $legacyId)->update(['category_id' => $newId]);

            DB::table('business_classifications')
                ->where('category_id', $legacyId)
                ->where('is_primary', true)
                ->update(['category_id' => $newId]);

            // Businesses may have both a legacy row and a new row after the
            // primary re-point; keep only the new primary row.
            $dupes = DB::table('business_classifications')
                ->where('category_id', $newId)
                ->where('is_primary', true)
                ->whereIn('business_id', function ($q) use ($legacyId) {
                    $q->select('business_id')
                        ->from('business_classifications')
                        ->where('category_id', $legacyId);
                })
                ->get();

            foreach ($dupes as $dupe) {
                DB::table('business_classifications')
                    ->where('business_id', $dupe->business_id)
                    ->where('category_id', $legacyId)
                    ->delete();
            }
        }

        // 4. Deactivate the known legacy roots (they may not carry a world_id
        //    in every environment) plus any other shop-world root that isn't
        //    one of the clean business types (e.g. an old `restaurants` or
        //    `electronics` root).
        foreach (['food-restaurants', 'shopping-retail', 'electronics-tech'] as $legacySlug) {
            DB::table('categories')->where('slug', $legacySlug)->update(['is_active' => false]);
        }

        $clean = ['grocery', 'food', 'medicine', 'general-shopping'];

        DB::table('categories')
            ->where('world_id', $shopWorldId)
            ->whereNull('parent_id')
            ->whereNotIn('slug', $clean)
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Taxonomy backfill — not reversed.
    }
};
