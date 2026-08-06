<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReclassifyBusinesses extends Command
{
    protected $signature = 'businesses:reclassify {--dry-run : Report changes without writing} {--only-inactive : Only fix businesses currently in inactive categories}';

    protected $description = 'Reclassify businesses stuck in generic/inactive categories (e.g. Establishment) using the keyword classifier';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $onlyInactive = $this->option('only-inactive');

        $businesses = Business::with('category')->whereNotNull('category_id')->get();

        $fixed = 0;
        $changed = [];

        foreach ($businesses as $business) {
            $current = $business->category;

            // Only touch businesses in inactive categories by default,
            // or any business whose keyword match disagrees.
            if ($current && $current->is_active) {
                if ($onlyInactive) {
                    continue;
                }
            }

            $match = classifyBusinessByKeywords(
                $business->name,
                is_array($business->photos) ? [] : [],
                $business->address ?? '',
            );

            if (! $match) {
                continue;
            }

            if ($current && $current->id === $match->id) {
                continue;
            }

            $changed[] = [
                'business' => $business->name,
                'from' => $current?->name ?? 'none',
                'to' => $match->name,
            ];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($business, $match) {
                $business->update(['category_id' => $match->id]);

                $classification = DB::table('business_classifications')
                    ->where('business_id', $business->id)
                    ->where('category_id', $match->id)
                    ->first();

                if ($classification) {
                    DB::table('business_classifications')
                        ->where('id', $classification->id)
                        ->update([
                            'is_primary' => 1,
                            'is_active' => 1,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('business_classifications')->insert([
                        'business_id' => $business->id,
                        'category_id' => $match->id,
                        'is_primary' => 1,
                        'is_active' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            $fixed++;
        }

        foreach ($changed as $c) {
            $this->line("  {$c['business']}: {$c['from']} -> {$c['to']}");
        }
        $this->info(($dryRun ? '[dry-run] ' : '')."Reclassified {$fixed} businesses.");

        return 0;
    }
}
