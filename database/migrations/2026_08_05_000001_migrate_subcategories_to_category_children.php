<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.4 — Migrate legacy `subcategories` into real category children.
 *
 * Each legacy subcategory row becomes a level-2 Category child of its parent
 * category (world_id inherited from the parent), and businesses.subcategory_id
 * is repointed at the new child row. The legacy `subcategories` table is left
 * untouched and becomes read-only (dropped in a later, separate migration).
 *
 * Idempotent: children are tracked via metadata.legacy_subcategory_id and the
 * migration's own row is tracked in the `migrations` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Repointed subcategory_id now references categories, not subcategories.
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
        });

        $subcategories = DB::table('subcategories')->orderBy('id')->get();
        if ($subcategories->isEmpty()) {
            return;
        }

        $parents = DB::table('categories')
            ->whereIn('id', $subcategories->pluck('category_id')->unique())
            ->get()
            ->keyBy('id');

        $alreadyMigrated = collect(DB::table('categories')->get(['metadata']))
            ->map(fn ($row) => json_decode($row->metadata, true))
            ->filter(fn ($meta) => is_array($meta) && ! empty($meta['migrated_from_subcategory']))
            ->map(fn ($meta) => (int) $meta['legacy_subcategory_id'])
            ->all();

        $now = now()->toDateTimeString();

        foreach ($subcategories as $sub) {
            if (in_array((int) $sub->id, $alreadyMigrated, true)) {
                continue;
            }

            $parent = $parents->get($sub->category_id);
            if (! $parent) {
                continue;
            }

            $newId = DB::table('categories')->insertGetId([
                'name' => $sub->name,
                'slug' => $this->uniqueSlug($sub->slug),
                'icon' => $sub->icon,
                'image' => null,
                'order' => (int) $sub->order,
                'is_featured' => false,
                'is_active' => (bool) $sub->is_active,
                'module_type' => $parent->module_type,
                'world_id' => $parent->world_id,
                'parent_id' => $parent->id,
                'description' => null,
                'level' => 2,
                'show_on_home' => false,
                'launch_phase' => $parent->launch_phase ?? 'phase1',
                'capability_template_id' => $parent->capability_template_id,
                'metadata' => json_encode([
                    'migrated_from_subcategory' => 1,
                    'legacy_subcategory_id' => (int) $sub->id,
                    'recommended_modules' => json_decode($sub->recommended_modules ?? 'null', true),
                ]),
                'business_type' => $parent->business_type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('businesses')
                ->where('subcategory_id', $sub->id)
                ->update(['subcategory_id' => $newId]);
        }
    }

    public function down(): void
    {
        $migrated = collect(DB::table('categories')->get(['id', 'metadata']))
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'legacy_id' => json_decode($row->metadata, true)['legacy_subcategory_id'] ?? null,
            ])
            ->filter(fn ($row) => $row->legacy_id !== null);

        foreach ($migrated as $child) {
            DB::table('businesses')
                ->where('subcategory_id', $child->id)
                ->update(['subcategory_id' => $child->legacy_id]);
            DB::table('categories')->where('id', $child->id)->delete();
        }

        // Values are legacy subcategory ids again, so the FK can be restored.
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('set null');
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $counter = 1;
        while (DB::table('categories')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($counter++);
        }

        return $slug;
    }
};
