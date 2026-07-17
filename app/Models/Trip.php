<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    protected $fillable = [
        'business_id',
        'vehicle_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'pickup_location',
        'drop_location',
        'pickup_lat',
        'pickup_lng',
        'drop_lat',
        'drop_lng',
        'distance_km',
        'fare',
        'seats_required',
        'driver_name',
        'driver_phone',
        'status',
        'trip_date',
        'trip_time',
        'booked_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'fare' => 'decimal:2',
        'seats_required' => 'integer',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'drop_lat' => 'decimal:7',
        'drop_lng' => 'decimal:7',
        'trip_date' => 'date',
        'booked_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed', 'booked_at' => now()]);
    }

    public function markStarted(): void
    {
        $this->update(['status' => 'started', 'started_at' => now()]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
