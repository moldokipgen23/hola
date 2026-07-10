<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'district',
        'state',
        'latitude',
        'longitude',
        'bounds_north',
        'bounds_south',
        'bounds_east',
        'bounds_west',
        'is_active',
        'business_count',
        'order',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'bounds_north' => 'float',
        'bounds_south' => 'float',
        'bounds_east' => 'float',
        'bounds_west' => 'float',
        'is_active' => 'boolean',
    ];

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a lat/lng falls within this area's bounds
     */
    public function contains(float $lat, float $lng): bool
    {
        if (!$this->bounds_north || !$this->bounds_south || !$this->bounds_east || !$this->bounds_west) {
            return false;
        }

        return $lat >= $this->bounds_south
            && $lat <= $this->bounds_north
            && $lng >= $this->bounds_west
            && $lng <= $this->bounds_east;
    }

    /**
     * Find the area that contains the given coordinates
     */
    public static function findByCoordinates(float $lat, float $lng): ?self
    {
        return static::active()
            ->where('bounds_south', '<=', $lat)
            ->where('bounds_north', '>=', $lat)
            ->where('bounds_west', '<=', $lng)
            ->where('bounds_east', '>=', $lng)
            ->first();
    }

    /**
     * Find area by name match (fuzzy)
     */
    public static function findByName(string $name): ?self
    {
        $safe = strtolower(trim($name));

        // Exact match first
        $area = static::active()->whereRaw('LOWER(name) = ?', [$safe])->first();
        if ($area) return $area;

        // Partial match
        $area = static::active()->whereRaw('LOWER(name) LIKE ?', ["%{$safe}%"])->first();
        if ($area) return $area;

        // Check aliases (stored as comma-separated in a column or just use the slug)
        $area = static::active()->whereRaw('LOWER(slug) LIKE ?', ["%{$safe}%"])->first();
        return $area;
    }
}
