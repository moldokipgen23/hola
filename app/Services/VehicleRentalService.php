<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Vehicle;
use App\Models\VehicleRental;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Per-date vehicle hire / rental: availability across a date range, booking
 * with locking, and the bookable tier (verified transport vendor only).
 */
class VehicleRentalService
{
    /**
     * Can this business offer online vehicle hire?
     */
    public function canOfferRental(Business $business): bool
    {
        return $business->hasModule('transport')
            && $business->verification_status === 'verified';
    }

    /**
     * Rental-capable vehicles (service_mode rental or goods) for a business.
     */
    public function rentalVehicles(Business $business, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $business->vehicles()
            ->where('is_active', true)
            ->where('service_mode', 'rental')
            ->get()
            ->map(fn (Vehicle $vehicle) => $this->withAvailability($vehicle, $from, $to));
    }

    /**
     * Add availability info (available dates / conflict count) to a vehicle.
     */
    public function withAvailability(Vehicle $vehicle, ?Carbon $from = null, ?Carbon $to = null): Vehicle
    {
        $vehicle->setAttribute('rental_available', $this->isAvailable($vehicle, $from, $to));
        $vehicle->setAttribute('rental_conflicts', $this->conflictsFor($vehicle, $from, $to));

        return $vehicle;
    }

    public function isAvailable(Vehicle $vehicle, ?Carbon $from = null, ?Carbon $to = null): bool
    {
        if (! $vehicle->is_active || $vehicle->service_mode !== 'rental' || ! $vehicle->price_per_day) {
            return false;
        }

        return $this->conflictsFor($vehicle, $from, $to) === 0;
    }

    /**
     * Count of overlapping active hires for a date range.
     */
    public function conflictsFor(Vehicle $vehicle, ?Carbon $from = null, ?Carbon $to = null): int
    {
        if (! $from || ! $to || $from->gt($to)) {
            return 0;
        }

        return $vehicle->rentals()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) use ($from, $to) {
                $q->whereDate('start_date', '<=', $to->toDateString())
                    ->whereDate('end_date', '>=', $from->toDateString());
            })
            ->count();
    }

    /**
     * Book a vehicle hire with row locking to prevent double booking.
     */
    public function book(
        Vehicle $vehicle,
        Carbon $start,
        Carbon $end,
        array $customer,
        bool $withDriver = false,
        bool $termsAccepted = false,
        ?int $userId = null,
        ?string $notes = null,
    ): VehicleRental {
        if ($start->lt(today())) {
            throw ValidationException::withMessages(['start_date' => 'Start date cannot be in the past.']);
        }
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'End date must be after the start date.']);
        }
        if ($vehicle->service_mode !== 'rental') {
            throw ValidationException::withMessages(['vehicle_id' => 'This vehicle is not offered for hire.']);
        }
        if (! $vehicle->price_per_day) {
            throw ValidationException::withMessages(['vehicle_id' => 'This vehicle is not available for hire.']);
        }
        if ($vehicle->terms && ! $termsAccepted) {
            throw ValidationException::withMessages(['terms_accepted' => 'You must accept the owner\'s terms & conditions to book this vehicle.']);
        }

        return DB::transaction(function () use ($vehicle, $start, $end, $customer, $withDriver, $termsAccepted, $userId, $notes) {
            $locked = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            $conflicts = $this->conflictsFor($locked, $start, $end);
            if ($conflicts > 0) {
                throw ValidationException::withMessages([
                    'date_range' => 'This vehicle is already booked for the selected dates. Choose different dates.',
                ]);
            }

            $days = max(1, (int) $start->diffInDays($end) + 1);
            $total = round((float) $locked->price_per_day * $days, 2);

            $rental = VehicleRental::create([
                'business_id' => $locked->business_id,
                'vehicle_id' => $locked->id,
                'user_id' => $userId,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'price_per_day' => $locked->price_per_day,
                'days' => $days,
                'total_price' => $total,
                'status' => 'pending',
                'payment_status' => 'pending',
                'with_driver' => $withDriver,
                'terms_accepted' => $termsAccepted,
                'notes' => $notes,
            ]);

            NotificationService::newVehicleRental($rental);

            return $rental;
        });
    }
}
