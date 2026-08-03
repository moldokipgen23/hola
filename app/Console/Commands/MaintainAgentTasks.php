<?php

namespace App\Console\Commands;

use App\Services\AgentTaskMaintenanceService;
use Illuminate\Console\Command;

class MaintainAgentTasks extends Command
{
    protected $signature = 'agent:maintain-tasks
        {--stale-minutes=60 : Fail pending or running tasks with no progress after this many minutes}
        {--dry-run : Report changes without writing them}';

    protected $description = 'Recover stale agent tasks, remove sensitive task input, and reconcile agent counters';

    public function handle(AgentTaskMaintenanceService $maintenance): int
    {
        $minutes = max(5, (int) $this->option('stale-minutes'));
        $dryRun = (bool) $this->option('dry-run');

        $stale = $maintenance->recoverStaleTasks($minutes, $dryRun);
        $sanitized = $maintenance->sanitizeStoredInputs($dryRun);
        $agents = $maintenance->reconcileAllAgents($dryRun);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Stale tasks recovered: {$stale}");
        $this->info("{$prefix}Task inputs sanitized: {$sanitized}");
        $this->info("{$prefix}Agent counters reconciled: {$agents}");

        return self::SUCCESS;
    }
}
