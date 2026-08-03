<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    protected $fillable = [
        'resource_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'buffer_minutes',
        'capacity',
        'blackout_dates',
        'booking_window_days',
        'minimum_notice_hours',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'blackout_dates' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }
}
