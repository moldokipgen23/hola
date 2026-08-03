<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaLibrary extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'user_id' => 'integer',
        'business_id' => 'integer',
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
}
