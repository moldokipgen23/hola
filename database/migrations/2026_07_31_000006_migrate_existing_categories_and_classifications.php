<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\World;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $worldMap = [
            'ordering' => 'shop',
            'both' => 'shop',
            'transport' => 'ride',
            'booking' => 'book',
            'turf' => 'book',
            'directory' => 'discover',
        ];

        foreach ($worldMap as $moduleType => $worldSlug) {
            $world = World::where('slug', $worldSlug)->first();
            if (! $world) {
                continue;
            }

            Category::where('module_type', $moduleType)
                ->whereNull('parent_id')
                ->update(['world_id' => $world->id]);
        }

        Category::where('module_type', 'ordering')
            ->whereNull('parent_id')
            ->whereNull('world_id')
            ->update(['world_id' => World::where('slug', 'shop')->value('id')]);

        Category::where('module_type', 'booking')
            ->whereNull('parent_id')
            ->whereNull('world_id')
            ->update(['world_id' => World::where('slug', 'book')->value('id')]);

        Category::where('module_type', 'directory')
            ->whereNull('parent_id')
            ->whereNull('world_id')
            ->update(['world_id' => World::where('slug', 'discover')->value('id')]);

        Category::query()->update(['level' => 0, 'show_on_home' => true, 'launch_phase' => 'phase1']);

        $this->migrateBusinessClassifications();
    }

    public function down(): void
    {
        DB::table('business_classifications')->where('source', 'system_migrated')->delete();
        Category::query()->update(['world_id' => null, 'parent_id' => null, 'level' => 0]);
    }

    private function migrateBusinessClassifications(): void
    {
        $businesses = Business::with(['category', 'subcategory'])->cursor();

        foreach ($businesses as $business) {
            if ($business->category_id) {
                $exists = DB::table('business_classifications')
                    ->where('business_id', $business->id)
                    ->where('category_id', $business->category_id)
                    ->exists();

                if (! $exists) {
                    DB::table('business_classifications')->insert([
                        'business_id' => $business->id,
                        'category_id' => $business->category_id,
                        'is_primary' => true,
                        'is_active' => true,
                        'source' => 'system_migrated',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
