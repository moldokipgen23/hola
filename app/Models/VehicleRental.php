<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleRental extends Model
{
    protected $fillable = [
        'business_id',
        'vehicle_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'start_date',
        'end_date',
        'price_per_day',
        'days',
        'total_price',
        'status',
        'payment_status',
        'with_driver',
        'terms_accepted',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_per_day' => 'decimal:2',
        'days' => 'integer',
        'total_price' => 'decimal:2',
        'with_driver' => 'boolean',
        'terms_accepted' => 'boolean',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        NotificationService::vehicleRentalStatusChanged($this, 'confirmed');
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
        NotificationService::vehicleRentalStatusChanged($this, 'completed');
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
        NotificationService::vehicleRentalStatusChanged($this, 'cancelled');
    }
}
