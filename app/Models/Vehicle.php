<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'type',
        'seats',
        'base_fare',
        'fare_per_km',
        'min_km',
        'registration_number',
        'image',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'seats' => 'integer',
        'base_fare' => 'decimal:2',
        'fare_per_km' => 'decimal:2',
        'min_km' => 'integer',
        'is_active' => 'boolean',
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
}
