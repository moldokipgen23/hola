<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'slug',
        'description',
        'address',
        'locality',
        'district',
        'latitude',
        'longitude',
        'phone',
        'whatsapp',
        'email',
        'website',
        'photos',
        'photos_downloaded_at',
        'working_hours',
        'claim_status',
        'verification_status',
        'source',
        'external_id',
        'import_batch_id',
        'confidence',
        'is_featured',
        'is_active',
        'views_count',
        'saves_count',
        'average_rating',
        'review_count',
        'call_count',
        'whatsapp_count',
        'directions_count',
        'share_count',
        'last_synced_at',
        'created_by',
    ];

    protected $casts = [
        'photos' => 'array',
        'working_hours' => 'array',
        'photos_downloaded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'views_count' => 'integer',
        'saves_count' => 'integer',
        'call_count' => 'integer',
        'whatsapp_count' => 'integer',
        'directions_count' => 'integer',
        'share_count' => 'integer',
        'average_rating' => 'float',
        'review_count' => 'integer',
    ];

    protected $appends = ['quality_score'];

    public function getQualityScoreAttribute(): int
    {
        $score = 0;
        if ($this->name) $score += 15;
        if ($this->description && strlen($this->description) > 20) $score += 15;
        if ($this->address) $score += 10;
        if ($this->phone) $score += 15;
        if ($this->latitude && $this->longitude) $score += 10;
        if ($this->working_hours) $score += 10;
        if ($this->photos && count($this->photos) > 0) $score += 15;
        if ($this->email) $score += 5;
        if ($this->whatsapp) $score += 5;
        return min($score, 100);
    }

    public function updateRatingStats(): void
    {
        $this->average_rating = round($this->reviews()->avg('rating'), 1);
        $this->review_count = $this->reviews()->count();
        $this->saveQuietly();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function savedByUsers(): HasMany
    {
        return $this->hasMany(SavedListing::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function claimRequests(): HasMany
    {
        return $this->hasMany(ClaimRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch($query, $term)
    {
        $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        return $query->where(function ($q) use ($safe) {
            $q->where('name', 'like', $safe)
              ->orWhere('description', 'like', $safe)
              ->orWhere('address', 'like', $safe);
        });
    }
}
