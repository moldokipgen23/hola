<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBooking extends Model
{
    protected $fillable = [
        'vehicle_schedule_id',
        'business_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'seat_labels',
        'seats',
        'total_price',
        'status',
        'payment_status',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'cancellation_reason',
        'client_reference',
        'notes',
    ];

    protected $casts = [
        'seat_labels' => 'array',
        'seats' => 'integer',
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(VehicleSchedule::class, 'vehicle_schedule_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        NotificationService::seatBookingStatusChanged($this, 'confirmed');
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
        NotificationService::seatBookingStatusChanged($this, 'completed');
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
        NotificationService::seatBookingStatusChanged($this, 'cancelled');
    }

    public function markNoShow(): void
    {
        $this->update(['status' => 'no_show']);
        NotificationService::seatBookingStatusChanged($this, 'no_show');
    }
}
