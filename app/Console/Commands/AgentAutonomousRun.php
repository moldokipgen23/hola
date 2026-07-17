<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Models\Business;
use App\Models\ImportItem;
use App\Models\Setting;
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
        $zipcodes = array_map('trim', explode(',', Setting::get('search_zipcodes', '795128')));
        $areas = array_map('trim', explode(',', Setting::get('search_areas', 'Lamka')));

        // Priority: Only these categories — real bookable businesses first
        $priority = [
            'restaurants', 'hotels', 'football turf', 'swimming pool',
            'resorts', 'schools', 'pharmacies', 'beauty salons',
        ];
        $queries = $priority;

        $searchQueries = [];
        foreach ($queries as $query) {
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

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $skillFilter = $this->option('skill');

        $agent = AiAgent::where('status', 'active')->first();
        if (! $agent) {
            $this->error('No active agent found.');

            return 1;
        }

        $this->info("🤖 Running autonomous pipeline for: {$agent->name}");

        $service = app(AgentSkillService::class);
        $results = [];
        $searchQueries = $this->getSearchQueries();

        // STEP 1: Search & Import (search 3 priority categories per run for faster coverage)
        if (! $skillFilter || $skillFilter === 'google_places_import') {
            $selected = count($searchQueries) <= 3
                ? $searchQueries
                : array_rand($searchQueries, 3);

            if (! is_array($selected)) {
                $selected = [$selected];
            }

            foreach ($selected as $idx) {
                $query = is_array($idx) ? $idx : $searchQueries[$idx];
                $this->info("  🔍 Searching: {$query['query']} in {$query['area']}");

                if (! $dryRun) {
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
                    } catch (\Exception $e) {
                        $this->error("  ❌ Search failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would search: {$query['query']}");
                }
            }
        }

        // STEP 2: Auto-categorize pending imports
        if (! $skillFilter || $skillFilter === 'auto_categorize') {
            $pendingCount = ImportItem::where('status', 'pending')->count();
            if ($pendingCount > 0) {
                $this->info("  📂 Categorizing {$pendingCount} pending items...");

                if (! $dryRun) {
                    $task = AiAgentTask::create([
                        'agent_id' => $agent->id,
                        'type' => 'auto_categorize',
                        'input' => ['scope' => 'all', 'max_results' => 30],
                        'status' => 'pending',
                    ]);

                    try {
                        $result = $service->run($agent, $task);
                        $results['categorize'] = $result;
                        $this->info("  ✅ Categorized {$result['imported']} | Created {$result['categories_created']} categories");
                    } catch (\Exception $e) {
                        $this->error("  ❌ Categorize failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would categorize {$pendingCount} items");
                }
            } else {
                $this->info('  📂 No pending items to categorize');
            }
        }

        // STEP 3: Quality check pending imports
        if (! $skillFilter || $skillFilter === 'quality_checker') {
            $pendingForQuality = ImportItem::where('status', 'pending')->count();
            if ($pendingForQuality > 0) {
                $this->info('  🔎 Running quality check...');

                if (! $dryRun) {
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
                    } catch (\Exception $e) {
                        $this->error("  ❌ Quality check failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would quality check {$pendingForQuality} items");
                }
            } else {
                $this->info('  🔎 No pending items for quality check');
            }
        }

        // STEP 4: Write descriptions for items without them
        if (! $skillFilter || $skillFilter === 'description_writer') {
            $noDesc = ImportItem::where('status', 'pending')
                ->whereNull('data->description')
                ->orWhere('data->description', '')
                ->count();

            if ($noDesc > 0) {
                $this->info("  ✍️ Writing descriptions for {$noDesc} items...");

                if (! $dryRun) {
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
                    } catch (\Exception $e) {
                        $this->error("  ❌ Description writing failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would write {$noDesc} descriptions");
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
                    $task = AiAgentTask::create([
                        'agent_id' => $agent->id,
                        'type' => 'google_sync',
                        'input' => ['limit' => 20],
                        'status' => 'pending',
                    ]);

                    try {
                        \Artisan::call('google:sync', ['--limit' => 20]);
                        $output = \Artisan::output();
                        $task->update(['status' => 'completed', 'output' => ['raw' => $output]]);
                        $this->info('  ✅ Google sync complete');
                    } catch (\Exception $e) {
                        $this->error("  ❌ Google sync failed: {$e->getMessage()}");
                    }
                } else {
                    $this->info("  [DRY RUN] Would sync {$importedCount} businesses with Google");
                }
            } else {
                $this->info('  🔄 No imported businesses to sync');
            }
        }

        $pending = ImportItem::where('status', 'pending')->count();
        $this->info('');
        $this->info("📊 Pipeline complete. Pending items for review: {$pending}");

        return 0;
    }
}
