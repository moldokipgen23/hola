<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    protected $table = 'search_history';

    protected $fillable = [
        'agent_id',
        'query',
        'area',
        'zipcode',
        'source',
        'total_found',
        'new_places',
        'already_imported',
        'duplicates',
        'place_ids',
    ];

    protected $casts = [
        'place_ids' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'agent_id');
    }
}
