<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'description',
        'image',
        'size_label',
        'price',
        'price_unit',
        'check_in_time',
        'check_out_time',
        'min_stay_nights',
        'max_stay_nights',
        'duration',
        'capacity',
        'inventory_units',
        'unit_label',
        'advance_booking_days',
        'cancellation_hours',
        'is_active',
        'sort_order',
        'has_fixed_slots',
        'booking_mode',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration' => 'integer',
        'capacity' => 'integer',
        'inventory_units' => 'integer',
        'min_stay_nights' => 'integer',
        'max_stay_nights' => 'integer',
        'advance_booking_days' => 'integer',
        'cancellation_hours' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'has_fixed_slots' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(BookableResource::class);
    }
}
