<?php

namespace App\Services;

use App\Models\AiAgent;

class AgentAssignmentService
{
    public function forSkill(string $skill): ?AiAgent
    {
        return AiAgent::active()
            ->get()
            ->filter(fn (AiAgent $agent) => $agent->hasSkill($skill))
            ->sortBy(fn (AiAgent $agent) => sprintf(
                '%010d-%s-%010d',
                $agent->tasks()->whereIn('status', ['pending', 'running'])->count(),
                $agent->last_active_at?->format('YmdHis') ?? '00000000000000',
                $agent->id,
            ))
            ->first();
    }
}
