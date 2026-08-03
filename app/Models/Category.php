<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Canonical mapping from the admin-facing bucket (module_type) to a single world.
     * A category belongs to exactly ONE world. The legacy `both` value is no longer
     * offered — a business that sells AND books is modelled at the business level.
     */
    public const MODULE_TYPE_WORLD = [
        'directory' => 'discover',
        'ordering' => 'shop',
        'booking' => 'book',
    ];

    /** Resolve the world_id for a given module_type bucket (null if unknown). */
    public static function worldIdForModuleType(?string $moduleType): ?int
    {
        $slug = self::MODULE_TYPE_WORLD[$moduleType] ?? null;

        return $slug ? World::query()->where('slug', $slug)->value('id') : null;
    }

    /**
     * Single write-path for taxonomy fields. Sets world_id + level from the tree
     * position: a child inherits its parent's world and sits one level deeper; a
     * root category derives its world from module_type and is level 1.
     * Mutates $data in place so both the standard form and the tree manager stay consistent.
     */
    public static function applyTaxonomy(array &$data, ?int $parentId = null): void
    {
        $data['parent_id'] = $parentId ?: null;

        if ($parentId && ($parent = self::find($parentId))) {
            $data['world_id'] = $parent->world_id;
            $data['level'] = (int) ($parent->level ?? 1) + 1;
        } else {
            $data['world_id'] = self::worldIdForModuleType($data['module_type'] ?? null);
            $data['level'] = 1;
        }
    }

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
