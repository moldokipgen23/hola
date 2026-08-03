<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'delivery_radius_km',
        'min_order_amount',
        'delivery_fee',
        'free_delivery_above',
        'estimated_time_minutes',
        'zones',
        'is_active',
    ];

    protected $casts = [
        'zones' => 'array',
        'is_active' => 'boolean',
        'delivery_radius_km' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'free_delivery_above' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
