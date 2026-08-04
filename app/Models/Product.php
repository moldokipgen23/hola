<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'business_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'menu_section',
        'food_type',
        'preparation_minutes',
        'available_from',
        'available_until',
        'sold_out_until',
        'image',
        'price',
        'availability',
        'stock',
        'is_active',
        'order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'stock' => 'integer',
        'preparation_minutes' => 'integer',
        'sold_out_until' => 'datetime',
    ];

    protected $appends = ['is_orderable', 'availability_message'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isCurrentlyOrderable(?Carbon $at = null): bool
    {
        $at ??= now();

        if (! $this->is_active || $this->availability === 'out_of_stock') {
            return false;
        }
        if ($this->stock !== null && $this->stock <= 0) {
            return false;
        }
        if ($this->sold_out_until && $this->sold_out_until->isFuture()) {
            return false;
        }

        return $this->isWithinAvailabilityWindow($at);
    }

    public function getIsOrderableAttribute(): bool
    {
        return $this->isCurrentlyOrderable();
    }

    public function getAvailabilityMessageAttribute(): string
    {
        if (! $this->is_active) {
            return 'Unavailable';
        }
        if ($this->sold_out_until && $this->sold_out_until->isFuture()) {
            return 'Sold out until '.$this->sold_out_until->format('d M, g:i A');
        }
        if ($this->availability === 'out_of_stock' || ($this->stock !== null && $this->stock <= 0)) {
            return 'Sold out';
        }
        if (! $this->isWithinAvailabilityWindow(now())) {
            return $this->available_from
                ? 'Available from '.Carbon::parse($this->available_from)->format('g:i A')
                : 'Not available now';
        }

        return 'Available';
    }

    private function isWithinAvailabilityWindow(Carbon $at): bool
    {
        if (! $this->available_from || ! $this->available_until) {
            return true;
        }

        $time = $at->format('H:i:s');
        $from = Carbon::parse($this->available_from)->format('H:i:s');
        $until = Carbon::parse($this->available_until)->format('H:i:s');

        return $from <= $until
            ? $time >= $from && $time <= $until
            : $time >= $from || $time <= $until;
    }
}
