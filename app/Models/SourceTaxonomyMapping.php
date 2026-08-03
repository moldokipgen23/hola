<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceTaxonomyMapping extends Model
{
    protected $fillable = [
        'provider',
        'source_type',
        'category_id',
        'subcategory_id',
        'recommended_modules',
        'confidence',
        'is_active',
    ];

    protected $casts = [
        'recommended_modules' => 'array',
        'confidence' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }
}
