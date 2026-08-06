<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyUnclassifiedBusinesses extends Command
{
    protected $signature = 'eiho:classify-unclassified';

    protected $description = 'Auto-classify businesses that have no active classification';

    public function handle(): int
    {
        $unclassified = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->whereNull('business_classifications.id')
            ->where('businesses.is_active', '=', 1)
            ->select('businesses.id', 'businesses.name', 'businesses.category_id', 'businesses.slug')
            ->get();

        if ($unclassified->isEmpty()) {
            $this->info('All active businesses are classified.');

            return 0;
        }

        $this->info("Found {$unclassified->count()} unclassified businesses.");

        // World mapping: category_id → world_id
        $worldMap = [
            1 => 1, // default to shop
        ];

        // Build category → world lookup from categories table
        $categoryWorlds = DB::table('categories')
            ->where('is_active', 1)
            ->pluck('world_id', 'id')
            ->toArray();

        $created = 0;
        $skipped = 0;

        foreach ($unclassified as $business) {
            $categoryId = $business->category_id;
            $worldId = $categoryWorlds[$categoryId] ?? 1;

            // Check if any classification exists (even inactive)
            $existing = DB::table('business_classifications')
                ->where('business_id', $business->id)
                ->first();

            if ($existing) {
                // Activate the existing one
                DB::table('business_classifications')
                    ->where('business_id', $business->id)
                    ->update(['is_active' => 1, 'is_primary' => 1]);
                $this->line("  Activated classification for: {$business->name} (ID: {$business->id})");
                $created++;
            } else {
                // Create new classification (world is derived via category.world_id)
                DB::table('business_classifications')->insert([
                    'business_id' => $business->id,
                    'category_id' => $categoryId,
                    'is_primary' => 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("  Created classification for: {$business->name} (ID: {$business->id}, cat: {$categoryId}, world: {$worldId})");
                $created++;
            }
        }

        $this->info("Done. Classified: {$created}, Skipped: {$skipped}");

        // Verify
        $remaining = DB::table('businesses')
            ->leftJoin('business_classifications', function ($j) {
                $j->on('businesses.id', '=', 'business_classifications.business_id')
                    ->where('business_classifications.is_active', '=', 1);
            })
            ->whereNull('business_classifications.id')
            ->where('businesses.is_active', '=', 1)
            ->count();

        $this->info("Remaining unclassified: {$remaining}");

        return 0;
    }
}
