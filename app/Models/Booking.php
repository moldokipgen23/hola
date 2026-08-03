<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_reference',
        'business_id',
        'service_id',
        'time_slot_id',
        'booking_type',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'booking_date',
        'check_in_date',
        'check_out_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'party_size',
        'reservation_units',
        'seat_labels',
        'unit_price',
        'total_price',
        'payment_status',
        'payment_method',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'confirmed_at',
        'completed_at',
        'rejection_reason',
        'rejected_at',
        'rescheduled_at',
        'rescheduled_to_date',
        'rescheduled_to_time',
        'reschedule_reason',
        'metadata',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'party_size' => 'integer',
        'reservation_units' => 'integer',
        'seat_labels' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'rescheduled_to_date' => 'date',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', now()->toDateString())
            ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('booking_date', $date);
    }

    public function markConfirmed(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function markRejected(?string $reason = null): void
    {
        $this->update(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $reason]);
    }

    public function markRescheduled(string $toDate, string $toTime, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rescheduled',
            'rescheduled_at' => now(),
            'rescheduled_to_date' => $toDate,
            'rescheduled_to_time' => $toTime,
            'reschedule_reason' => $reason,
        ]);
    }
}
