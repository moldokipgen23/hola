<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldHomepageContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'world_id',
        'section_type',
        'title',
        'subtitle',
        'image_url',
        'link_url',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForWorld($query, int $worldId)
    {
        return $query->where('world_id', $worldId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('section_type', $type);
    }
}
