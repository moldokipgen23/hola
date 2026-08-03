<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'image',
        'order',
        'is_featured',
        'is_active',
        'module_type',
        'is_canonical',
        // New hierarchy fields
        'world_id',
        'parent_id',
        'description',
        'level',
        'show_on_home',
        'launch_phase',
        'capability_template_id',
        'metadata',
        'business_type',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'is_canonical' => 'boolean',
        'show_on_home' => 'boolean',
        'metadata' => 'array',
    ];

    // Legacy relationship
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    // New hierarchy relationships
    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function capabilityTemplate(): BelongsTo
    {
        return $this->belongsTo(CapabilityTemplate::class);
    }

    public function filters(): HasMany
    {
        return $this->hasMany(CategoryFilter::class);
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(BusinessClassification::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInWorld($query, int $worldId)
    {
        return $query->where('world_id', $worldId);
    }

    public function scopeForPhase($query, string $phase)
    {
        return $query->where('launch_phase', $phase);
    }

    public function scopeShowOnHome($query)
    {
        return $query->where('show_on_home', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    // Helper methods
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    public function getAncestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors->reverse();
    }

    public function getBreadcrumbsAttribute(): string
    {
        return $this->getAncestors()
            ->push($this)
            ->pluck('name')
            ->implode(' > ');
    }

    public function getDepthAttribute(): int
    {
        return $this->getAncestors()->count();
    }
}
