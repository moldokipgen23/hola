<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CATEGORIZED = 'categorized';

    public const STATUS_REVIEW = 'review';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_MERGED = 'merged';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Non-terminal statuses still awaiting human review. */
    public const IN_PIPELINE = [
        self::STATUS_PENDING,
        self::STATUS_CATEGORIZED,
        self::STATUS_REVIEW,
    ];

    protected $fillable = [
        'batch_id',
        'data',
        'status',
        'business_id',
        'duplicate_of',
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

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'duplicate_of');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAwaitingCategorization($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCategorized($query)
    {
        return $query->where('status', self::STATUS_CATEGORIZED);
    }

    public function scopeReview($query)
    {
        return $query->where('status', self::STATUS_REVIEW);
    }

    public function scopeInPipeline($query)
    {
        return $query->whereIn('status', self::IN_PIPELINE);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }
}
