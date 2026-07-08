<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'agent_id',
        'source',
        'name',
        'total',
        'imported',
        'approved',
        'rejected',
        'skipped',
        'status',
        'config',
        'error',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class, 'batch_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
