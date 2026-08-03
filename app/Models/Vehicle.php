<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $appends = ['is_requestable'];

    protected $fillable = [
        'business_id',
        'name',
        'type',
        'service_mode',
        'seats',
        'capacity_value',
        'capacity_unit',
        'base_fare',
        'fare_per_km',
        'requires_quote',
        'min_km',
        'registration_number',
        'image',
        'description',
        'availability_status',
        'next_available_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'seats' => 'integer',
        'capacity_value' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'fare_per_km' => 'decimal:2',
        'min_km' => 'integer',
        'is_active' => 'boolean',
        'requires_quote' => 'boolean',
        'next_available_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function estimatedFare(float $distanceKm): float
    {
        $distance = max($distanceKm, $this->min_km);

        return $this->base_fare + ($distance * $this->fare_per_km);
    }

    public function isRequestable(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && $this->availability_status !== 'offline'
            && ($this->availability_status !== 'busy' || $at->gt(now()->addMinutes(5)))
            && (! $this->next_available_at || $this->next_available_at->lte($at));
    }

    public function getIsRequestableAttribute(): bool
    {
        return $this->isRequestable();
    }
}
