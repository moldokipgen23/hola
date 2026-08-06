<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingWorkflowService
{
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'rejected'],
        'confirmed' => ['completed', 'cancelled', 'no_show'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
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
            $locked->load('business');

            // Record platform commission when a booking completes.
            if ($status === 'completed' && $locked->business) {
                try {
                    app(MonetizationService::class)->recordCommission(
                        $locked->business,
                        Booking::class,
                        $locked->id,
                        (float) ($locked->total_price ?? 0),
                    );
                } catch (\Throwable $e) {
                    // Commission must never fail a workflow transition.
                }
            }

            try {
                NotificationService::bookingStatusChanged($locked, $status);
            } catch (\Throwable $e) {
                // Notifications must never fail a workflow transition.
            }

            return $locked->fresh()->load('service');
        });
    }

    public function cancelByCustomer(Booking $booking, ?string $reason = null): Booking
    {
        if ($booking->checked_in_at) {
            throw ValidationException::withMessages([
                'reason' => 'This guest has already checked in. Contact the business to check out or adjust.',
            ]);
        }

        $hours = (int) ($booking->service?->cancellation_hours ?? 0);
        $startsAt = Carbon::parse($booking->booking_date->toDateString().' '.$booking->start_time->format('H:i'));
        if ($hours > 0 && now()->diffInHours($startsAt, false) < $hours) {
            throw ValidationException::withMessages([
                'reason' => "Online cancellation closes {$hours} hours before the booking. Contact the business directly.",
            ]);
        }

        return $this->transition($booking, 'cancelled', $reason);
    }

    public function reschedule(
        Booking $booking,
        string $toDate,
        ?string $toTime = null,
        ?string $toSlotId = null,
        ?string $reason = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $toDate, $toSlotId, $toTime, $reason) {
            $locked = Booking::lockForUpdate()->with('service')->findOrFail($booking->id);
            $target = Carbon::parse($toDate)->startOfDay();
            if ($target->lt(today())) {
                throw ValidationException::withMessages(['to_date' => 'Choose today or a future date.']);
            }

            $service = Service::whereKey($locked->service_id)->lockForUpdate()->first();
            $advanceDays = $service?->advance_booking_days ?? 60;
            if ($target->gt(today()->addDays($advanceDays))) {
                throw ValidationException::withMessages(['to_date' => "This service accepts bookings up to {$advanceDays} days ahead."]);
            }

            $oldSchedule = [
                'booking_date' => $locked->booking_date?->toDateString(),
                'check_in_date' => $locked->check_in_date?->toDateString(),
                'check_out_date' => $locked->check_out_date?->toDateString(),
                'start_time' => $locked->start_time?->format('H:i'),
                'end_time' => $locked->end_time?->format('H:i'),
                'time_slot_id' => $locked->time_slot_id,
                'party_size' => $locked->party_size,
                'reservation_units' => $locked->reservation_units,
            ];

            $updates = [
                'rescheduled_at' => now(),
                'reschedule_reason' => $reason,
                'rescheduled_to_date' => $target,
                'rescheduled_to_time' => $toTime,
            ];

            if ($locked->booking_type === 'stay') {
                $checkOut = Carbon::parse($toDate)->addDays(max(1, (int) $locked->check_in_date?->diffInDays($locked->check_out_date)));
                $inventory = max(1, (int) ($service?->inventory_units ?? 1));
                $reserved = (int) Booking::where('service_id', $locked->service_id)
                    ->whereKeyNot($locked->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('check_in_date', '<', $checkOut)
                    ->where('check_out_date', '>', $target)
                    ->sum('reservation_units');
                if ($reserved + $locked->reservation_units > $inventory) {
                    throw ValidationException::withMessages(['to_date' => 'Not enough units remain on the new dates.']);
                }
                $updates['booking_date'] = $target;
                $updates['check_in_date'] = $target;
                $updates['check_out_date'] = $checkOut;
            } elseif ($toSlotId && in_array($locked->booking_type, ['time_slot', 'standard', 'seat'], true)) {
                $slot = TimeSlot::where('service_id', $locked->service_id)
                    ->whereKey($toSlotId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $slot || ($slot->day_of_week !== null && $slot->day_of_week !== $target->dayOfWeek)) {
                    throw ValidationException::withMessages(['to_slot_id' => 'This slot is not offered on the selected date.']);
                }
                $isSeat = $locked->booking_type === 'seat' || $locked->service?->booking_mode === 'seat';
                $slotMode = $locked->service?->booking_mode === 'slot';
                $consumption = $slotMode ? $locked->reservation_units : $locked->party_size;

                $reserved = (int) Booking::where('time_slot_id', $slot->id)
                    ->whereKeyNot($locked->id)
                    ->whereDate('booking_date', $target)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum($slotMode ? 'reservation_units' : 'party_size');
                if ($reserved + $consumption > $slot->capacity) {
                    throw ValidationException::withMessages(['to_slot_id' => 'This slot does not have enough space on the new date.']);
                }

                // Re-validate seat labels for seat bookings on the target slot.
                if ($isSeat && $locked->seat_labels) {
                    $taken = Booking::where('time_slot_id', $slot->id)
                        ->whereKeyNot($locked->id)
                        ->whereDate('booking_date', $target)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->get(['seat_labels'])
                        ->flatMap(fn (Booking $b) => $b->seat_labels ?? [])
                        ->map(fn ($s) => (string) $s);
                    if ($taken->intersect($locked->seat_labels)->isNotEmpty()) {
                        throw ValidationException::withMessages(['to_slot_id' => 'One or more seats are already booked on the new slot.']);
                    }
                }

                $updates['booking_date'] = $target;
                $updates['time_slot_id'] = $slot->id;
                $updates['booking_type'] = 'time_slot';
                $updates['start_time'] = $slot->start_time;
                $updates['end_time'] = $slot->end_time;
                $updates['duration_minutes'] = (int) Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($slot->end_time));
                $unitPrice = (float) ($slot->price_override ?? $service?->price ?? 0);
                $updates['unit_price'] = $unitPrice;
                $updates['total_price'] = $unitPrice * ($isSeat ? $locked->party_size : ($slotMode ? $locked->reservation_units : 1));
            } elseif (in_array($locked->booking_type, ['time_slot', 'standard'], true) && $toTime) {
                $start = Carbon::parse($target->toDateString().' '.$toTime);
                if ($start->isPast()) {
                    throw ValidationException::withMessages(['to_time' => 'Choose a future time.']);
                }
                $duration = max(15, (int) ($service?->duration ?? 60));
                $end = $start->copy()->addMinutes($duration);
                $capacity = max(1, (int) ($service?->capacity ?? 1));
                $reserved = (int) Booking::where('service_id', $locked->service_id)
                    ->whereKeyNot($locked->id)
                    ->whereDate('booking_date', $target)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('start_time', '<', $end->format('H:i'))
                    ->where('end_time', '>', $start->format('H:i'))
                    ->sum('party_size');
                if ($reserved + $locked->party_size > $capacity) {
                    throw ValidationException::withMessages(['to_time' => 'This time does not have enough availability.']);
                }
                $updates['booking_date'] = $target;
                $updates['start_time'] = $start->format('H:i');
                $updates['end_time'] = $end->format('H:i');
                $updates['duration_minutes'] = $duration;
            } else {
                throw ValidationException::withMessages(['to_date' => 'Provide a new date with a time or slot.']);
            }

            $updates['status'] = 'confirmed';
            $updates['confirmed_at'] = now();
            $updates['rejection_reason'] = null;
            $updates['rejected_at'] = null;
            $updates['metadata'] = array_merge($locked->metadata ?? [], [
                'previous_schedule' => $oldSchedule,
                'rescheduled_at' => now()->toDateTimeString(),
            ]);

            $locked->update($updates);

            try {
                NotificationService::bookingStatusChanged($locked, 'rescheduled');
            } catch (\Throwable $e) {
                // Notifications must never fail a workflow transition.
            }

            return $locked->fresh()->load('service');
        });
    }

    public function markCashCollected(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if (! in_array($locked->status, ['confirmed', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Only confirmed or completed bookings can be marked as paid.',
                ]);
            }
            $locked->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

            return $locked->fresh();
        });
    }

    public function checkIn(Booking $booking, ?string $roomNumber = null): Booking
    {
        return DB::transaction(function () use ($booking, $roomNumber) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($locked->booking_type !== 'stay') {
                throw ValidationException::withMessages(['booking_type' => 'Only stay bookings can be checked in.']);
            }
            if ($locked->status !== 'confirmed') {
                throw ValidationException::withMessages(['status' => 'Only confirmed bookings can be checked in.']);
            }
            if ($locked->checked_in_at) {
                throw ValidationException::withMessages(['checked_in_at' => 'Guest is already checked in.']);
            }
            if ($locked->check_in_date && $locked->check_in_date->gt(today())) {
                throw ValidationException::withMessages(['check_in_date' => 'Guest cannot check in before the scheduled check-in date.']);
            }
            $locked->update([
                'checked_in_at' => now(),
                'room_number' => $roomNumber,
            ]);

            return $locked->fresh();
        });
    }

    public function checkOut(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($locked->booking_type !== 'stay') {
                throw ValidationException::withMessages(['booking_type' => 'Only stay bookings can be checked out.']);
            }
            if (! $locked->checked_in_at) {
                throw ValidationException::withMessages(['checked_in_at' => 'Guest must be checked in first.']);
            }
            if ($locked->checked_out_at) {
                throw ValidationException::withMessages(['checked_out_at' => 'Guest is already checked out.']);
            }
            $locked->update([
                'checked_out_at' => now(),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
