<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'business_id',
        'rating',
        'comment',
        'owner_response',
        'status',
        'moderation_reason',
        'flagged_at',
        'photo',
    ];

    protected $casts = [
        'rating' => 'integer',
        'owner_response' => 'string',
        'status' => 'string',
        'flagged_at' => 'datetime',
    ];

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HIDDEN = 'hidden';

    protected static function booted(): void
    {
        static::saved(function (Review $review) {
            $review->business->updateRatingStats();
        });

        static::deleted(function (Review $review) {
            $review->business->updateRatingStats();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
