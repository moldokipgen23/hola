<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingWorkflowService
{
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'rejected', 'rescheduled'],
        'confirmed' => ['completed', 'cancelled', 'no_show', 'rescheduled'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
        'rescheduled' => [],
        'no_show' => [],
    ];

    public function transition(Booking $booking, string $status, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $status) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if (! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from '{$locked->status}' to '{$status}'.",
                ]);
            }

            $updates = ['status' => $status];
            if ($status === 'cancelled') {
                $updates['cancellation_reason'] = $reason;
                $updates['cancelled_at'] = now();
            } elseif ($status === 'rejected') {
                $updates['rejection_reason'] = $reason;
                $updates['rejected_at'] = now();
            } elseif ($status === 'rescheduled') {
                $updates['rescheduled_at'] = now();
            } elseif ($status === 'confirmed') {
                $updates['confirmed_at'] = now();
            } elseif ($status === 'completed') {
                $updates['completed_at'] = now();
            }

            $locked->update($updates);

            return $locked->fresh()->load('service');
        });
    }

    public function cancelByCustomer(Booking $booking, ?string $reason = null): Booking
    {
        $hours = (int) ($booking->service?->cancellation_hours ?? 0);
        $startsAt = Carbon::parse($booking->booking_date->toDateString().' '.$booking->start_time->format('H:i'));
        if ($hours > 0 && now()->diffInHours($startsAt, false) < $hours) {
            throw ValidationException::withMessages([
                'reason' => "Online cancellation closes {$hours} hours before the booking. Contact the business directly.",
            ]);
        }

        return $this->transition($booking, 'cancelled', $reason);
    }

    public function markCashCollected(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['payment_status' => 'A cancelled booking cannot be marked as paid.']);
            }
            $locked->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

            return $locked->fresh();
        });
    }
}
