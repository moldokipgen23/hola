<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentTask extends Model
{
    protected $fillable = [
        'agent_id',
        'type',
        'input',
        'output',
        'status',
        'result_count',
        'imported_count',
        'search_metadata',
        'cost',
        'duration_ms',
        'error',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'search_metadata' => 'array',
        'cost' => 'decimal:4',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
