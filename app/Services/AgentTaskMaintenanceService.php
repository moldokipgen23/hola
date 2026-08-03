<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Support\AgentTaskInputSanitizer;

class AgentTaskMaintenanceService
{
    public function recoverStaleTasks(int $minutes, bool $dryRun = false): int
    {
        $cutoff = now()->subMinutes($minutes);
        $tasks = AiAgentTask::query()
            ->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '<=', $cutoff)
            ->get();

        if (! $dryRun) {
            foreach ($tasks as $task) {
                $task->update([
                    'status' => 'failed',
                    'error' => "Recovered stale {$task->status} task after {$minutes} minutes without progress.",
                ]);
            }
        }

        return $tasks->count();
    }

    public function sanitizeStoredInputs(bool $dryRun = false): int
    {
        $changed = 0;

        AiAgentTask::query()->orderBy('id')->chunkById(200, function ($tasks) use (&$changed, $dryRun) {
            foreach ($tasks as $task) {
                $sanitized = AgentTaskInputSanitizer::sanitize($task->input ?? []);

                if ($sanitized === ($task->input ?? [])) {
                    continue;
                }

                $changed++;

                if (! $dryRun) {
                    $task->input = $sanitized;
                    $task->save();
                }
            }
        });

        return $changed;
    }

    public function reconcileAgent(AiAgent $agent, bool $dryRun = false): array
    {
        $metrics = [
            'tasks_completed' => $agent->tasks()->completed()->count(),
            'tasks_failed' => $agent->tasks()->failed()->count(),
            'total_cost' => (float) $agent->tasks()->sum('cost'),
            'last_active_at' => $agent->tasks()
                ->whereIn('status', ['completed', 'failed'])
                ->max('updated_at'),
        ];

        if (! $dryRun) {
            $agent->forceFill($metrics)->save();
        }

        return $metrics;
    }

    public function reconcileAllAgents(bool $dryRun = false): int
    {
        $count = 0;

        AiAgent::query()->each(function (AiAgent $agent) use (&$count, $dryRun) {
            $this->reconcileAgent($agent, $dryRun);
            $count++;
        });

        return $count;
    }
}
