<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'billable_type',
        'billable_id',
        'type',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billable()
    {
        return $this->morphTo();
    }
}
