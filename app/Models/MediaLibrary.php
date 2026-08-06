<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaLibrary extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'media_library';

    protected $fillable = [
        'user_id',
        'business_id',
        'filename',
        'original_filename',
        'mime_type',
        'size_bytes',
        'path',
        'disk',
        'alt_text',
        'category',
        'sort_order',
        'is_cover',
        'status',
        'moderation_reason',
        'flagged_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'user_id' => 'integer',
        'business_id' => 'integer',
        'sort_order' => 'integer',
        'is_cover' => 'boolean',
        'flagged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeOfType($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('is_cover', 'desc')->orderBy('sort_order')->orderByDesc('id');
    }
}
