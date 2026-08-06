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
        'seat_layout',
        'capacity_value',
        'capacity_unit',
        'base_fare',
        'fare_per_km',
        'price_per_day',
        'requires_quote',
        'min_km',
        'registration_number',
        'image',
        'description',
        'terms',
        'availability_status',
        'next_available_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'seats' => 'integer',
        'seat_layout' => 'array',
        'capacity_value' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'fare_per_km' => 'decimal:2',
        'price_per_day' => 'decimal:2',
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

    public function rentals(): HasMany
    {
        return $this->hasMany(VehicleRental::class);
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

    /**
     * The seat map for this vehicle. Returns the vendor's visual layout when
     * present (RedBus/aeroplane style), otherwise a simple numbered grid so a
     * seat picker still works. Each seat is ['label' => ..., 'row' => ...,
     * 'col' => ..., 'deck' => ..., 'type' => ...].
     */
    public function seatMap(): array
    {
        if (is_array($this->seat_layout) && count($this->seat_layout) > 0) {
            return $this->seat_layout;
        }

        return $this->defaultSeatMap();
    }

    public function hasVisualSeatLayout(): bool
    {
        return is_array($this->seat_layout) && count($this->seat_layout) > 0;
    }

    /**
     * Fallback grid: columns of seats labelled 1..N in rows of 4 (2-2), which
     * maps cleanly to the classic bus/aeroplane layout.
     */
    public function defaultSeatMap(): array
    {
        $total = max((int) $this->seats, 1);
        $seats = [];
        $label = 1;
        $row = 1;

        while ($label <= $total) {
            for ($col = 1; $col <= 4 && $label <= $total; $col++) {
                $type = in_array($col, [1, 4], true) ? 'window' : 'aisle';
                $seats[] = [
                    'label' => (string) $label,
                    'row' => $row,
                    'col' => $col,
                    'deck' => 'lower',
                    'type' => $type,
                ];
                $label++;
            }
            $row++;
        }

        return $seats;
    }
}
