<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchAnalytic extends Model
{
    protected $fillable = [
        'query',
        'user_id',
        'world',
        'results_count',
        'clicked_business_id',
        'clicked_position',
        'session_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'clicked_business_id');
    }
}
