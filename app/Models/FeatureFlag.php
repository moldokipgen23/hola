<?php

namespace App\Models;

use App\Services\LaunchControlService;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => LaunchControlService::clearCache());
        static::deleted(fn () => LaunchControlService::clearCache());
    }
    protected $fillable = [
        'key',
        'name',
        'description',
        'group',
        'is_enabled',
        'is_visible_to_customers',
        'launch_phase',
        'enabled_areas',
        'enabled_businesses',
        'enabled_categories',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_visible_to_customers' => 'boolean',
        'enabled_areas' => 'array',
        'enabled_businesses' => 'array',
        'enabled_categories' => 'array',
        'metadata' => 'array',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForCustomers($query)
    {
        return $query->where('is_visible_to_customers', true);
    }

    public function scopeInPhase($query, string $phase)
    {
        return $query->where('launch_phase', $phase);
    }

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public static function isEnabled(string $key): bool
    {
        $flag = static::where('key', $key)->first();

        return $flag?->is_enabled ?? false;
    }

    public static function visibleToCustomers(string $key): bool
    {
        $flag = static::where('key', $key)->first();

        return $flag?->is_visible_to_customers ?? false;
    }
}
