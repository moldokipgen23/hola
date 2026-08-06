<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    protected $fillable = [
        'origin',
        'destination',
        'distance_km',
        'base_fare',
        'estimated_minutes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'base_fare' => 'decimal:2',
        'estimated_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class);
    }

    public function getTitleAttribute(): string
    {
        return "{$this->origin} → {$this->destination}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('origin')->orderBy('destination');
    }

    public function scopeSearch($query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn ($q) => $q->where('origin', 'like', $safe)->orWhere('destination', 'like', $safe));
    }
}
