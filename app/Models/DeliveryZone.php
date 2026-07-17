<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    protected $fillable = [
        'business_id',
        'area_id',
        'pincodes',
        'min_order_amount',
        'delivery_fee',
        'estimated_minutes',
        'is_active',
    ];

    protected $casts = [
        'min_order_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'estimated_minutes' => 'integer',
        'is_active' => 'boolean',
        'pincodes' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
