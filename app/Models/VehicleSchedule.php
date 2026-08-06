<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleSchedule extends Model
{
    protected $fillable = [
        'business_id',
        'vehicle_id',
        'transport_route_id',
        'origin',
        'destination',
        'distance_km',
        'estimated_minutes',
        'departure_date',
        'departure_time',
        'seats_capacity',
        'price',
        'status',
        'notes',
        'boarding_stops',
        'drop_stops',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'departure_time' => 'string',
        'distance_km' => 'decimal:2',
        'estimated_minutes' => 'integer',
        'seats_capacity' => 'integer',
        'price' => 'decimal:2',
        'boarding_stops' => 'array',
        'drop_stops' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ScheduleBooking::class);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('departure_date', '>=', now()->toDateString());
    }

    public function getDepartureAtAttribute(): string
    {
        return "{$this->departure_date->format('Y-m-d')} {$this->departure_time}";
    }

    public function getTitleAttribute(): string
    {
        return "{$this->origin} → {$this->destination}";
    }
}
