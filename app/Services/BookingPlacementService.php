<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingPlacementService
{
    public function place(
        Business $business,
        int $serviceId,
        array $customer,
        array $schedule,
        ?int $userId = null,
        ?string $clientReference = null,
    ): array {
        try {
            return DB::transaction(function () use ($business, $clientReference, $customer, $schedule, $serviceId, $userId) {
                if ($clientReference) {
                    $existing = Booking::where('client_reference', $clientReference)->lockForUpdate()->first();
                    if ($existing) {
                        $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

                        return ['booking' => $existing->load('service'), 'duplicate' => true];
                    }
                }

                $service = Service::where('business_id', $business->id)
                    ->whereKey($serviceId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $service) {
                    throw ValidationException::withMessages(['service_id' => 'Choose an active service from this business.']);
                }

                $mode = $service->booking_mode ?: 'appointment';
                $date = Carbon::parse($schedule['check_in_date'] ?? $schedule['booking_date'])->startOfDay();
                if ($date->lt(today())) {
                    throw ValidationException::withMessages(['booking_date' => 'Choose today or a future date.']);
                }

                $advanceDays = $service->advance_booking_days ?? 60;
                if ($date->gt(today()->addDays($advanceDays))) {
                    throw ValidationException::withMessages([
                        'booking_date' => "This service accepts bookings up to {$advanceDays} days ahead.",
                    ]);
                }

                $partySize = (int) ($schedule['party_size'] ?? 1);
                $reservationUnits = (int) ($schedule['reservation_units'] ?? 1);
                $seatLabels = collect($schedule['seat_labels'] ?? [])
                    ->map(fn ($seat) => strtoupper(trim((string) $seat)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $slot = null;
                $checkOutDate = null;
                $unitPrice = (float) $service->price;

                if ($mode === 'stay') {
                    if (empty($schedule['check_out_date'])) {
                        throw ValidationException::withMessages(['check_out_date' => 'Choose a check-out date.']);
                    }
                    $checkOutDate = Carbon::parse($schedule['check_out_date'])->startOfDay();
                    if ($checkOutDate->lte($date)) {
                        throw ValidationException::withMessages(['check_out_date' => 'Check-out must be after check-in.']);
                    }
                    $nights = (int) $date->diffInDays($checkOutDate);
                    if ($nights < max(1, (int) $service->min_stay_nights)) {
                        throw ValidationException::withMessages(['check_out_date' => "This stay requires at least {$service->min_stay_nights} night(s)."]);
                    }
                    if ($service->max_stay_nights && $nights > $service->max_stay_nights) {
                        throw ValidationException::withMessages(['check_out_date' => "This stay allows up to {$service->max_stay_nights} nights."]);
                    }
                    $inventory = max(1, (int) $service->inventory_units);
                    $reserved = (int) Booking::where('service_id', $service->id)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->where('check_in_date', '<', $checkOutDate)
                        ->where('check_out_date', '>', $date)
                        ->sum('reservation_units');
                    if ($reservationUnits > $inventory || $reserved + $reservationUnits > $inventory) {
                        throw ValidationException::withMessages(['reservation_units' => 'Not enough rooms or units remain for these dates.']);
                    }
                    $startTime = $service->check_in_time ?: '14:00';
                    $endTime = $service->check_out_time ?: '11:00';
                    $duration = $nights * 1440;
                    $price = $unitPrice * $nights * $reservationUnits;
                } elseif ($service->has_fixed_slots || in_array($mode, ['slot', 'seat'], true)) {
                    if (empty($schedule['time_slot_id'])) {
                        throw ValidationException::withMessages(['time_slot_id' => 'Choose an available time slot.']);
                    }

                    $slot = TimeSlot::where('service_id', $service->id)
                        ->whereKey($schedule['time_slot_id'])
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $slot || ($slot->day_of_week !== null && $slot->day_of_week !== $date->dayOfWeek)) {
                        throw ValidationException::withMessages(['time_slot_id' => 'This slot is not offered on the selected date.']);
                    }

                    $consumption = $mode === 'seat' ? $partySize : ($mode === 'slot' ? $reservationUnits : $partySize);
                    $reserved = (int) Booking::where('time_slot_id', $slot->id)
                        ->whereDate('booking_date', $date)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->sum($mode === 'slot' ? 'reservation_units' : 'party_size');
                    if ($reserved + $consumption > $slot->capacity) {
                        throw ValidationException::withMessages(['time_slot_id' => 'This time slot does not have enough space left.']);
                    }

                    if ($mode === 'seat' && $seatLabels) {
                        if (count($seatLabels) !== $partySize) {
                            throw ValidationException::withMessages(['seat_labels' => 'Choose one unique seat for each guest.']);
                        }
                        $takenSeats = Booking::where('time_slot_id', $slot->id)
                            ->whereDate('booking_date', $date)
                            ->whereIn('status', ['pending', 'confirmed'])
                            ->get(['seat_labels'])
                            ->flatMap(fn (Booking $booking) => $booking->seat_labels ?? [])
                            ->map(fn ($seat) => strtoupper((string) $seat));
                        if ($takenSeats->intersect($seatLabels)->isNotEmpty()) {
                            throw ValidationException::withMessages(['seat_labels' => 'One or more selected seats are no longer available.']);
                        }
                    }

                    $startTime = $slot->start_time;
                    $endTime = $slot->end_time;
                    $duration = $this->minutesBetween($date, $startTime, $endTime);
                    if (Carbon::parse($date->toDateString().' '.$startTime)->isPast()) {
                        throw ValidationException::withMessages(['time_slot_id' => 'Choose a future time slot.']);
                    }
                    $unitPrice = (float) ($slot->price_override ?? $service->price);
                    $price = $unitPrice * ($mode === 'seat' ? $partySize : ($mode === 'slot' ? $reservationUnits : 1));
                } else {
                    $startTime = $schedule['start_time'] ?? null;
                    if (! $startTime) {
                        throw ValidationException::withMessages(['start_time' => 'Choose a start time.']);
                    }

                    $start = Carbon::parse($date->toDateString().' '.$startTime);
                    if ($start->isPast()) {
                        throw ValidationException::withMessages(['start_time' => 'Choose a future time.']);
                    }

                    $duration = max(15, (int) $service->duration);
                    $endTime = $start->copy()->addMinutes($duration)->format('H:i');
                    $startTime = $start->format('H:i');
                    $capacity = max(1, (int) ($service->capacity ?? 1));
                    $reserved = (int) Booking::where('service_id', $service->id)
                        ->whereDate('booking_date', $date)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime)
                        ->sum('party_size');

                    if ($reserved + $partySize > $capacity) {
                        throw ValidationException::withMessages(['start_time' => 'This time does not have enough availability.']);
                    }

                    $price = $service->price;
                }

                $booking = Booking::create([
                    'client_reference' => $clientReference,
                    'business_id' => $business->id,
                    'service_id' => $service->id,
                    'time_slot_id' => $slot?->id,
                    'booking_type' => match ($mode) {
                        'stay' => 'stay',
                        'seat' => 'seat',
                        'slot' => 'time_slot',
                        default => $slot ? 'time_slot' : 'standard',
                    },
                    'user_id' => $userId,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'] ?? null,
                    'booking_date' => $date,
                    'check_in_date' => $mode === 'stay' ? $date : null,
                    'check_out_date' => $checkOutDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $duration,
                    'party_size' => $partySize,
                    'reservation_units' => $reservationUnits,
                    'seat_labels' => $seatLabels ?: null,
                    'unit_price' => $unitPrice,
                    'total_price' => $price,
                    'payment_status' => 'pending',
                    'payment_method' => 'cash',
                    'status' => 'pending',
                    'notes' => $customer['notes'] ?? null,
                    'metadata' => ['payment_mode' => 'offline'],
                ]);

                return ['booking' => $booking->load('service'), 'duplicate' => false];
            });
        } catch (QueryException $exception) {
            $existing = $clientReference
                ? Booking::where('client_reference', $clientReference)->first()
                : null;

            if (! $existing) {
                throw $exception;
            }

            $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

            return ['booking' => $existing->load('service'), 'duplicate' => true];
        }
    }

    private function minutesBetween(Carbon $date, string $start, string $end): int
    {
        $from = Carbon::parse($date->toDateString().' '.$start);
        $to = Carbon::parse($date->toDateString().' '.$end);
        if ($to->lte($from)) {
            throw ValidationException::withMessages(['time_slot_id' => 'This time slot has an invalid duration.']);
        }

        return (int) $from->diffInMinutes($to);
    }

    private function assertReferenceBelongsToRequest(
        Booking $booking,
        Business $business,
        array $customer,
        ?int $userId,
    ): void {
        $wrongUser = ($booking->user_id !== null || $userId !== null)
            && $booking->user_id !== $userId;

        if ($booking->business_id !== $business->id
            || $wrongUser
            || $booking->customer_phone !== $customer['phone']) {
            throw ValidationException::withMessages([
                'client_reference' => 'This booking reference is already in use.',
            ]);
        }
    }
}
