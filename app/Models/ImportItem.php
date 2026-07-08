<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    protected $fillable = [
        'batch_id',
        'data',
        'status',
        'business_id',
        'external_id',
        'notes',
        'confidence',
    ];

    protected $casts = [
        'data' => 'array',
        'confidence' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
