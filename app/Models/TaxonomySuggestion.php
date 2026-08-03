<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxonomySuggestion extends Model
{
    protected $fillable = [
        'agent_id',
        'import_item_id',
        'business_id',
        'suggestion_type',
        'suggested_name',
        'suggested_parent_id',
        'source_provider',
        'source_type',
        'evidence',
        'confidence',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'evidence' => 'array',
        'confidence' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    public function importItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function suggestedParent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_parent_id');
    }
}
