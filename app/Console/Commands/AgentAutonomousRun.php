<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\Business;
use App\Models\ImportItem;
use App\Models\Setting;
use App\Services\AgentAssignmentService;
use App\Services\AgentSkillService;
use Illuminate\Console\Command;

class AgentAutonomousRun extends Command
{
    protected $signature = 'agent:auto-run {--skill=} {--dry-run}';

    protected $description = 'Run AI agent skills autonomously on schedule';

    // Pre-configured search queries — pulled from Settings (supports multiple areas + zipcodes)
    private function getSearchQueries(): array
    {
        $district = Setting::get('search_district', 'Churachandpur');
        $state = Setting::get('search_state', 'Manipur');
        $zipcodes = array_values(array_filter(array_map('trim', explode(',', Setting::get('search_zipcodes', '795128'))))) ?: ['795128'];
        $areas = array_values(array_filter(array_map('trim', explode(',', Setting::get('search_areas', 'Lamka'))))) ?: ['Lamka'];

        // TIER 1 (highest priority) — private / professional / bookable services.
        // Imported first and most often. These are the revenue-driving businesses.
        $tier1 = [
            'restaurants', 'cafes', 'bakery', 'fast food',
            'hotels', 'guest houses', 'lodges', 'resorts', 'homestays',
            'salons', 'beauty parlour', 'barber shop', 'spa',
            'clinics', 'dental clinic', 'pharmacy', 'diagnostic lab',
            'schools', 'colleges', 'tuition center', 'preschool',
            'gym', 'football turf', 'swimming pool', 'sports',
            'electrician', 'plumber', 'cleaning service', 'mechanic', 'car repair',
            'electronics store', 'mobile shop', 'computer store', 'hardware store',
            'grocery store', 'supermarket', 'tailor', 'laundry', 'photographer',
            'catering', 'event venue', 'travel agent', 'tour operator',
        ];

        // TIER 2 — general retail, services and professional offices.
        $tier2 = [
            'lawyer', 'accountant', 'consultant', 'real estate', 'insurance agent',
            'bank', 'atm', 'courier', 'logistics', 'printing press',
            'furniture store', 'jewellery store', 'clothing store', 'shoe store',
            'book store', 'stationery', 'gift shop', 'pet store',
            'car dealer', 'bike shop', 'tire shop', 'auto parts',
            'beauty parlor', 'nursing home', 'physiotherapy', 'optometrist',
        ];

        // TIER 3 (lowest priority) — government / public / community places.
        // Imported only after the private & professional businesses are covered.
        $tier3 = [
            'government office', 'police station', 'post office', 'public library',
            'community hall', 'church', 'temple', 'mosque', 'public park',
        ];

        // Use setting override when provided (comma separated, tier=query pairs).
        $custom = Setting::get('agent_search_queries', '');
        $priority = $custom
            ? array_values(array_filter(array_map('trim', explode(',', $custom))))
            : array_merge($tier1, $tier2, $tier3);

        $searchQueries = [];
        foreach ($priority as $query) {
            // Pick a random area + zipcode combo for each query
            $area = $areas[array_rand($areas)];
            $zip = $zipcodes[array_rand($zipcodes)];
            $searchQueries[] = [
                'query' => $query,
                'area' => "{$area}, {$district}, {$state} {$zip}",
            ];
        }

        return $searchQueries;
    }

    public function handle(AgentAssignmentService $assignments, AgentSkillService $service): int
    {
        $dryRun = $this->option('dry-run');
        $skillFilter = $this->option('skill');

        if (! AiAgent::active()->exists()) {
            $this->error('No active agent found.');

            return 1;
        }

        $supportedSkills = ['google_places_import', 'serpapi_business_search', 'auto_categorize', 'quality_checker', 'description_writer', 'google_sync'];
        if ($skillFilter && ! in_array($skillFilter, $supportedSkills, true)) {
            $this->error('Unsupported Autopilot skill: '.$skillFilter);

            return self::FAILURE;
        }

        $this->info('🤖 Running autonomous pipeline with skill-aware assignment');
        $results = [];
        $failures = 0;
        $searchQueries = $this->getSearchQueries();

        // STEP 1: Search & Import (3 priority categories per run, tier-aware).
        // Tier-1 (private/professional) queries are always chosen first; Tier-3
        // (government/public) queries only run once higher-priority ones exist.
        if (! $skillFilter || $skillFilter === 'google_places_import') {
            $tier1 = array_slice($searchQueries, 0, 40);
            $rest = array_slice($searchQueries, 40);

            $selected = [];
            if (count($tier1) >= 3) {
                $picked = array_rand($tier1, min(3, count($tier1)));
                $selected = is_array($picked) ? array_map(fn ($i) => $tier1[$i], $picked) : [$tier1[$picked]];
            } elseif (count($tier1) > 0) {
                $selected = $tier1;
            }

            $remainingSlots = 3 - count($selected);
            if ($remainingSlots > 0 && count($rest) > 0) {
                $picked = array_rand($rest, min($remainingSlots, count($rest)));
                $picked = is_array($picked) ? array_map(fn ($i) => $rest[$i], $picked) : [$rest[$picked]];
                $selected = array_merge($selected, $picked);
            }

            foreach ($selected as $query) {
                $this->info("  🔍 Searching: {$query['query']} in {$query['area']}");

                if (! $dryRun) {
                    $agent = $assignments->forSkill('google_places_import');
                    if (! $agent) {
                        $this->error('  ❌ No active agent supports google_places_import');
                        $failures++;

                        continue;
                    }
                    $task = AiAgentTask::create([
                        'agent_id' => $agent->id,
                        'type' => 'google_places_import',
                        'input' => array_merge($query, ['max_results' => 20]),
                        'status' => 'pending',
                    ]);

                    try {
                        $result = $service->run($agent, $task);
                        $results[] = $result;
                        $this->info("  ✅ Found {$result['count']} | Imported {$result['imported']} | Duplicates {$result['duplicates']}");
                    } catch (\Throwable $e) {
                        $failures++;
                        $this->error("  ❌ Search failed: {$e->getMessage()}");
                    }
                } else {
                    $agent = $assignments->forSkill('google_places_import');
                    if (! $agent) {
                        $failures++;
                    }
                    $this->info("  [DRY RUN] Would assign {$query['query']} to ".($agent?->name ?? 'NO ELIGIBLE AGENT'));
                }
            }
        }

        // STEP 2: Auto-categorize pending imports
        if (! $skillFilter || $skillFilter === 'auto_categorize') {
            $pendingCount = ImportItem::awaitingCategorization()->count();
            if ($pendingCount > 0) {
                $this->info("  📂 Categorizing {$pendingCount} pending items...");

                if (! $dryRun) {
                    $agent = $assignments->forSkill('auto_categorize');
                    if (! $agent) {
                        $this->error('  ❌ No active agent supports auto_categorize');
                        $failures++;
                    } else {
                        $task = AiAgentTask::create([
                            'agent_id' => $agent->id,
                            'type' => 'auto_categorize',
                            'input' => ['scope' => 'pending', 'max_results' => 30],
                            'status' => 'pending',
                        ]);

                        try {
                            $result = $service->run($agent, $task);
                            $results['categorize'] = $result;
                            $this->info("  ✅ Categorized {$result['imported']} | Suggested {$result['suggestions_created']} taxonomy changes");
                        } catch (\Throwable $e) {
                            $failures++;
                            $this->error("  ❌ Categorize failed: {$e->getMessage()}");
                        }
                    }
                } else {
                    $agent = $assignments->forSkill('auto_categorize');
                    if (! $agent) {
                        $failures++;
                    }
                    $this->info("  [DRY RUN] Would assign {$pendingCount} items to ".($agent?->name ?? 'NO ELIGIBLE AGENT'));
                }
            } else {
                $this->info('  📂 No pending items to categorize');
            }
        }

        // STEP 3: Quality check categorized imports
        if (! $skillFilter || $skillFilter === 'quality_checker') {
            $pendingForQuality = ImportItem::categorized()->count();
            if ($pendingForQuality > 0) {
                $this->info('  🔎 Running quality check...');

                if (! $dryRun) {
                    $agent = $assignments->forSkill('quality_checker');
                    if (! $agent) {
                        $this->error('  ❌ No active agent supports quality_checker');
                        $failures++;
                    } else {
                        $task = AiAgentTask::create([
                            'agent_id' => $agent->id,
                            'type' => 'quality_checker',
                            'input' => ['max_results' => 30],
                            'status' => 'pending',
                        ]);

                        try {
                            $result = $service->run($agent, $task);
                            $results['quality'] = $result;
                            $this->info("  ✅ Checked {$result['count']} | Passed {$result['imported']}");
                        } catch (\Throwable $e) {
                            $failures++;
                            $this->error("  ❌ Quality check failed: {$e->getMessage()}");
                        }
                    }
                } else {
                    $agent = $assignments->forSkill('quality_checker');
                    if (! $agent) {
                        $failures++;
                    }
                    $this->info('  [DRY RUN] Would assign quality check to '.($agent?->name ?? 'NO ELIGIBLE AGENT'));
                }
            } else {
                $this->info('  🔎 No pending items for quality check');
            }
        }

        // STEP 4: Write descriptions for items without them
        if (! $skillFilter || $skillFilter === 'description_writer') {
            $noDesc = ImportItem::inPipeline()
                ->where(function ($query) {
                    $query->whereNull('data->description')->orWhere('data->description', '');
                })
                ->count();

            if ($noDesc > 0) {
                $this->info("  ✍️ Writing descriptions for {$noDesc} items...");

                if (! $dryRun) {
                    $agent = $assignments->forSkill('description_writer');
                    if (! $agent) {
                        $this->error('  ❌ No active agent supports description_writer');
                        $failures++;
                    } else {
                        $task = AiAgentTask::create([
                            'agent_id' => $agent->id,
                            'type' => 'description_writer',
                            'input' => ['max_results' => 10],
                            'status' => 'pending',
                        ]);

                        try {
                            $result = $service->run($agent, $task);
                            $results['descriptions'] = $result;
                            $this->info("  ✅ Wrote {$result['imported']} descriptions");
                        } catch (\Throwable $e) {
                            $failures++;
                            $this->error("  ❌ Description writing failed: {$e->getMessage()}");
                        }
                    }
                } else {
                    $agent = $assignments->forSkill('description_writer');
                    if (! $agent) {
                        $failures++;
                    }
                    $this->info('  [DRY RUN] Would assign descriptions to '.($agent?->name ?? 'NO ELIGIBLE AGENT'));
                }
            } else {
                $this->info('  ✍️ All items have descriptions');
            }
        }

        // STEP 5: Google Sync — detect changes to already-imported businesses
        if (! $skillFilter || $skillFilter === 'google_sync') {
            $importedCount = Business::where('source', 'import')
                ->where('is_active', true)
                ->whereNotNull('external_id')
                ->count();

            if ($importedCount > 0) {
                $this->info("  🔄 Syncing {$importedCount} businesses with Google...");

                if (! $dryRun) {
                    try {
                        $exitCode = \Artisan::call('google:sync', ['--limit' => 20]);
                        $output = \Artisan::output();

                        if ($exitCode !== self::SUCCESS) {
                            throw new \RuntimeException(trim($output) ?: 'Google sync failed.');
                        }

                        $this->info('  ✅ Google sync complete');
                    } catch (\Throwable $e) {
                        $failures++;
                        $this->error("  ❌ Google sync failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would sync {$importedCount} businesses with Google");
                }
            } else {
                $this->info('  🔄 No imported businesses to sync');
            }
        }

        // STEP 6: SerpAPI business search — only when explicitly requested, so a
        // plain autonomous run keeps using the primary Google Places pipeline.
        if ($skillFilter === 'serpapi_business_search') {
            $docs = $this->getSearchQueries();
            $selected = array_slice($docs, 0, 3);

            foreach ($selected as $query) {
                $this->info("  🔍 SerpAPI searching: {$query['query']} in {$query['area']}");

                if (! $dryRun) {
                    $agent = $assignments->forSkill('serpapi_business_search');
                    if (! $agent) {
                        $this->error('  ❌ No active agent supports serpapi_business_search');
                        $failures++;

                        continue;
                    }
                    $task = AiAgentTask::create([
                        'agent_id' => $agent->id,
                        'type' => 'serpapi_business_search',
                        'input' => array_merge($query, ['max_results' => 20]),
                        'status' => 'pending',
                    ]);

                    try {
                        $result = $service->run($agent, $task);
                        $results[] = $result;
                        $this->info("  ✅ Found {$result['count']} | Imported {$result['imported']}");
                    } catch (\Throwable $e) {
                        $failures++;
                        $this->error("  ❌ SerpAPI search failed: {$e->getMessage()}");
                    }
                } else {
                    $agent = $assignments->forSkill('serpapi_business_search');
                    if (! $agent) {
                        $failures++;
                    }
                    $this->info('  [DRY RUN] Would run SerpAPI search on '.($agent?->name ?? 'NO ELIGIBLE AGENT'));
                }
            }
        }

        $pending = ImportItem::inPipeline()->count();
        $this->info('');
        $this->info("📊 Pipeline complete. {$pending} items ready for review.");

        if ($failures > 0) {
            $this->error("Pipeline completed with {$failures} failure(s).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
