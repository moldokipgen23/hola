<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ScheduleBooking;
use App\Models\VehicleSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transport seat booking: availability per schedule, seat reservation with
 * locking, and the two-tier availability rule.
 *
 * Tier 1 (bookable)  — verified transport vendor with a transport module and
 *                      at least one active schedule: customers book seats and
 *                      get a real confirmation.
 * Tier 2 (call-only) — any other transport/travel business surfaced from the
 *                      directory: no online booking, contact the business.
 */
class TransportAvailabilityService
{
    /**
     * Can this business accept online seat bookings?
     */
    public function isBookable(Business $business): bool
    {
        if (! $business->hasModule('transport')) {
            return false;
        }

        if ($business->verification_status !== 'verified') {
            return false;
        }

        return $business->schedules()->where('status', 'scheduled')->exists();
    }

    /**
     * Whether an unbookable transport business should still surface in the
     * transport section as a call-only listing.
     */
    public function isDirectoryTransport(Business $business): bool
    {
        return $business->hasModule('transport')
            && $business->verification_status !== 'verified';
    }

    /**
     * Available seats (labels) for a schedule, accounting for all
     * pending + confirmed bookings.
     */
    public function availableSeats(VehicleSchedule $schedule): array
    {
        $capacity = max((int) $schedule->seats_capacity, 1);
        $map = $schedule->vehicle?->seatMap() ?? [];

        if (empty($map)) {
            $map = collect(range(1, $capacity))->map(fn ($n) => [
                'label' => (string) $n, 'row' => 1, 'col' => $n, 'deck' => 'lower', 'type' => 'aisle',
            ])->all();
        }

        $taken = $this->takenSeatLabels($schedule);

        return collect($map)
            ->reject(fn (array $seat) => in_array((string) ($seat['label'] ?? ''), $taken, true))
            ->values()
            ->all();
    }

    /**
     * Full seat map annotated with availability, for the seat-picker UI.
     */
    public function seatMapWithAvailability(VehicleSchedule $schedule): array
    {
        $map = $schedule->vehicle?->seatMap() ?? [];
        $taken = $this->takenSeatLabels($schedule);

        return collect($map)->map(function (array $seat) use ($taken) {
            $seat['available'] = ! in_array((string) ($seat['label'] ?? ''), $taken, true);

            return $seat;
        })->all();
    }

    public function remainingSeats(VehicleSchedule $schedule): int
    {
        return max(0, (int) $schedule->seats_capacity - count($this->takenSeatLabels($schedule)));
    }

    /**
     * Book seats on a schedule with row locking to prevent double booking.
     * Throws ValidationException if any selected seat is already taken.
     */
    public function bookSeats(
        VehicleSchedule $schedule,
        array $seatLabels,
        array $customer,
        ?int $userId = null,
        ?string $clientReference = null,
        ?string $notes = null,
    ): ScheduleBooking {
        return DB::transaction(function () use ($schedule, $seatLabels, $customer, $userId, $clientReference, $notes) {
            if ($clientReference) {
                $existing = ScheduleBooking::where('client_reference', $clientReference)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lockedSchedule = VehicleSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($lockedSchedule->status !== 'scheduled') {
                throw ValidationException::withMessages(['schedule' => 'This departure is no longer available.']);
            }

            if ($lockedSchedule->departure_date->lt(now()->toDateString())) {
                throw ValidationException::withMessages(['schedule' => 'This departure has already passed.']);
            }

            $labels = array_values(array_unique(array_map(fn ($s) => (string) $s, $seatLabels)));
            if (empty($labels)) {
                throw ValidationException::withMessages(['seat_labels' => 'Choose at least one seat.']);
            }

            $capacity = (int) $lockedSchedule->seats_capacity;
            if (count($labels) > $capacity) {
                throw ValidationException::withMessages([
                    'seat_labels' => 'You selected '.count($labels)." seats but this vehicle has only {$capacity} seats.",
                ]);
            }

            $seatMap = $lockedSchedule->vehicle?->seatMap();
            if (is_array($seatMap) && count($seatMap) > 0) {
                $validLabels = collect($seatMap)->pluck('label')->map(fn ($l) => (string) $l)->all();
                $invalid = array_values(array_diff($labels, $validLabels));
                if ($invalid) {
                    throw ValidationException::withMessages([
                        'seat_labels' => 'Seat(s) '.implode(', ', $invalid).' do not exist on this vehicle. Pick from the seat map.',
                    ]);
                }
            }

            $this->assertSeatsAvailable($lockedSchedule, $labels);

            $seats = count($labels);
            $price = (float) $lockedSchedule->price;

            $booking = ScheduleBooking::create([
                'vehicle_schedule_id' => $lockedSchedule->id,
                'business_id' => $lockedSchedule->business_id,
                'user_id' => $userId,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
                'seat_labels' => $labels,
                'seats' => $seats,
                'total_price' => round($price * $seats, 2),
                'status' => 'pending',
                'payment_status' => 'pending',
                'client_reference' => $clientReference,
                'notes' => $notes,
            ]);

            NotificationService::newSeatBooking($booking);

            return $booking;
        });
    }

    private function assertSeatsAvailable(VehicleSchedule $schedule, array $labels): void
    {
        $taken = $this->takenSeatLabels($schedule);

        $conflicts = array_values(array_intersect($labels, $taken));
        if ($conflicts) {
            throw ValidationException::withMessages([
                'seat_labels' => 'Seat(s) '.implode(', ', $conflicts).' are already booked. Pick from the available seats.',
            ]);
        }
    }

    /**
     * Seat labels currently reserved (pending + confirmed) on a schedule.
     * Expired pending bookings (older than 30 min) are actively cancelled so
     * their seats are truly freed and the DB stays consistent with availability.
     */
    private function takenSeatLabels(VehicleSchedule $schedule): array
    {
        $schedule->bookings()
            ->where('status', 'pending')
            ->whereNotNull('created_at')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'cancelled', 'cancellation_reason' => 'Seat request expired (unconfirmed after 30 minutes).']);

        return $schedule->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->flatMap(fn (ScheduleBooking $booking) => (array) $booking->seat_labels)
            ->map(fn ($label) => (string) $label)
            ->values()
            ->all();
    }
}
