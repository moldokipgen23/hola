<?php

namespace App\Services;

use App\Models\AiAgentTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalHealthService
{
    public function snapshot(): array
    {
        $schedulerLastRun = Cache::get('health:scheduler:last_run_at');
        $queueLastRun = Cache::get('health:queue:last_run_at');
        $queueIsSynchronous = config('queue.default') === 'sync';

        $checks = [
            'application' => true,
            'database' => $this->databaseIsHealthy(),
            'storage' => is_dir(storage_path()) && is_writable(storage_path()),
            'scheduler' => $this->heartbeatIsRecent($schedulerLastRun),
            'queue' => $queueIsSynchronous
                ? 'not_required'
                : $this->heartbeatIsRecent($queueLastRun),
        ];

        $healthy = $checks['database']
            && $checks['storage']
            && $checks['scheduler']
            && $checks['queue'] !== false;

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'heartbeats' => [
                'scheduler' => $schedulerLastRun,
                'queue' => $queueIsSynchronous ? null : $queueLastRun,
            ],
            'queue_backlog' => $this->queueBacklog(),
            'agent_tasks' => $this->agentTaskStats(),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function databaseIsHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function queueBacklog(): ?int
    {
        if (config('queue.default') === 'sync' || ! Schema::hasTable('jobs')) {
            return null;
        }

        try {
            return DB::table('jobs')->count();
        } catch (\Throwable) {
            return null;
        }
    }

    private function agentTaskStats(): array
    {
        if (! Schema::hasTable('ai_agent_tasks')) {
            return [
                'pending' => 0,
                'running' => 0,
                'failed_last_24_hours' => 0,
                'stale' => 0,
            ];
        }

        return [
            'pending' => AiAgentTask::pending()->count(),
            'running' => AiAgentTask::running()->count(),
            'failed_last_24_hours' => AiAgentTask::failed()
                ->where('updated_at', '>=', now()->subDay())
                ->count(),
            'stale' => AiAgentTask::query()
                ->whereIn('status', ['pending', 'running'])
                ->where('updated_at', '<=', now()->subHour())
                ->count(),
        ];
    }

    private function heartbeatIsRecent(mixed $lastRun): bool
    {
        if (! $lastRun) {
            return false;
        }

        try {
            return Carbon::parse($lastRun)->isAfter(now()->subMinutes(5));
        } catch (\Throwable) {
            return false;
        }
    }
}
