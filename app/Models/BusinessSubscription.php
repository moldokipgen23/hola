<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function isActive(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }
        if ($this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->lt(now())) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->lt(now())) {
            return false;
        }

        return in_array($this->status, ['active', 'trialing'], true);
    }
}
