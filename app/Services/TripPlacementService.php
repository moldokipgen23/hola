<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripPlacementService
{
    public function place(
        Business $business,
        int $vehicleId,
        array $customer,
        array $journey,
        ?int $userId = null,
        ?string $clientReference = null,
    ): array {
        try {
            return DB::transaction(function () use ($business, $clientReference, $customer, $journey, $userId, $vehicleId) {
                if ($clientReference) {
                    $existing = Trip::where('client_reference', $clientReference)->lockForUpdate()->first();
                    if ($existing) {
                        $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

                        return ['trip' => $existing->load('vehicle'), 'duplicate' => true];
                    }
                }

                $vehicle = Vehicle::where('business_id', $business->id)
                    ->whereKey($vehicleId)
                    ->lockForUpdate()
                    ->first();
                if (! $vehicle) {
                    throw ValidationException::withMessages(['vehicle_id' => 'This vehicle is not accepting requests now.']);
                }

                $requestType = $this->requestType($vehicle->service_mode);
                $scheduledAt = $this->scheduledAt($journey['scheduled_at'] ?? null, $requestType);
                if (! $vehicle->isRequestable($scheduledAt)) {
                    throw ValidationException::withMessages(['vehicle_id' => 'This vehicle is not available at the requested time.']);
                }
                $returnAt = ! empty($journey['return_at']) ? Carbon::parse($journey['return_at']) : null;
                if ($returnAt && $returnAt->lte($scheduledAt)) {
                    throw ValidationException::withMessages(['return_at' => 'Return time must be after pickup time.']);
                }

                $distance = isset($journey['distance_km']) ? (float) $journey['distance_km'] : null;
                $duration = $returnAt
                    ? max(30, (int) $scheduledAt->diffInMinutes($returnAt))
                    : max(30, $distance ? (int) ceil(($distance / 30) * 60) : 120);
                $endAt = $returnAt ?? $scheduledAt->copy()->addMinutes($duration);
                $seats = (int) ($journey['seats_required'] ?? 1);

                if ($requestType === 'rental' && ! $returnAt) {
                    throw ValidationException::withMessages(['return_at' => 'Choose a return date and time for a rental.']);
                }
                if ($requestType === 'goods' && empty(trim((string) ($journey['load_description'] ?? '')))) {
                    throw ValidationException::withMessages(['load_description' => 'Describe the goods or load.']);
                }

                $this->assertCapacity($vehicle, $scheduledAt, $endAt, $seats, $journey['load_weight'] ?? null);

                $requiresQuote = $vehicle->requires_quote
                    || in_array($requestType, ['goods', 'rental'], true)
                    || ! $distance;
                $fare = $distance ? round($vehicle->estimatedFare($distance), 2) : 0;

                $trip = Trip::create([
                    'client_reference' => $clientReference,
                    'business_id' => $business->id,
                    'vehicle_id' => $vehicle->id,
                    'request_type' => $requestType,
                    'user_id' => $userId,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'] ?? null,
                    'pickup_location' => $journey['pickup_location'],
                    'drop_location' => $journey['drop_location'],
                    'pickup_lat' => $journey['pickup_lat'] ?? null,
                    'pickup_lng' => $journey['pickup_lng'] ?? null,
                    'drop_lat' => $journey['drop_lat'] ?? null,
                    'drop_lng' => $journey['drop_lng'] ?? null,
                    'distance_km' => $distance,
                    'fare' => $fare,
                    'fare_status' => $requiresQuote ? 'quote_required' : 'estimated',
                    'seats_required' => $seats,
                    'load_weight' => $journey['load_weight'] ?? null,
                    'load_description' => $journey['load_description'] ?? null,
                    'trip_date' => $scheduledAt->toDateString(),
                    'trip_time' => $scheduledAt->format('H:i'),
                    'scheduled_at' => $scheduledAt,
                    'return_at' => $returnAt,
                    'estimated_duration_minutes' => $duration,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => 'cash',
                    'notes' => $customer['notes'] ?? null,
                ]);

                NotificationService::newTrip($trip);

                return ['trip' => $trip->load('vehicle'), 'duplicate' => false];
            });
        } catch (QueryException $exception) {
            $existing = $clientReference ? Trip::where('client_reference', $clientReference)->first() : null;
            if (! $existing) {
                throw $exception;
            }
            $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

            return ['trip' => $existing->load('vehicle'), 'duplicate' => true];
        }
    }

    private function scheduledAt(?string $value, string $requestType): Carbon
    {
        $scheduledAt = $value ? Carbon::parse($value) : now();
        if ($scheduledAt->lt(now()->subMinutes(5))) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a current or future pickup time.']);
        }
        if ($scheduledAt->gt(now()->addYear())) {
            throw ValidationException::withMessages(['scheduled_at' => 'Transport requests can be scheduled up to one year ahead.']);
        }
        if ($requestType !== 'ride' && ! $value) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a pickup date and time.']);
        }

        return $scheduledAt;
    }

    private function assertCapacity(Vehicle $vehicle, Carbon $start, Carbon $end, int $seats, mixed $loadWeight): void
    {
        if ($vehicle->service_mode !== 'goods' && $seats > $vehicle->seats) {
            throw ValidationException::withMessages(['seats_required' => 'The requested passengers exceed this vehicle’s seating capacity.']);
        }

        if ($vehicle->service_mode === 'shared') {
            $reserved = (int) Trip::where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['pending', 'confirmed', 'started'])
                ->whereBetween('scheduled_at', [$start->copy()->subMinutes(5), $start->copy()->addMinutes(5)])
                ->sum('seats_required');
            if ($reserved + $seats > $vehicle->seats) {
                throw ValidationException::withMessages(['seats_required' => 'Not enough seats remain for this departure.']);
            }

            return;
        }

        if ($vehicle->service_mode === 'goods' && $vehicle->capacity_value && $loadWeight) {
            if ((float) $loadWeight > (float) $vehicle->capacity_value) {
                throw ValidationException::withMessages(['load_weight' => 'The load exceeds this vehicle’s capacity.']);
            }
        }

        $conflict = Trip::where('vehicle_id', $vehicle->id)
            ->whereIn('status', ['pending', 'confirmed', 'started'])
            ->where('scheduled_at', '<', $end)
            ->get(['scheduled_at', 'estimated_duration_minutes', 'return_at'])
            ->contains(function (Trip $trip) use ($start) {
                $tripEnd = $trip->return_at
                    ?? $trip->scheduled_at?->copy()->addMinutes($trip->estimated_duration_minutes ?? 120);

                return $tripEnd && $tripEnd->gt($start);
            });
        if ($conflict) {
            throw ValidationException::withMessages(['scheduled_at' => 'This vehicle already has a request during that time.']);
        }
    }

    private function requestType(?string $serviceMode): string
    {
        return match ($serviceMode) {
            'shared' => 'shared',
            'rental' => 'rental',
            'goods' => 'goods',
            default => 'ride',
        };
    }

    private function assertReferenceBelongsToRequest(Trip $trip, Business $business, array $customer, ?int $userId): void
    {
        $wrongUser = ($trip->user_id !== null || $userId !== null) && $trip->user_id !== $userId;
        if ($trip->business_id !== $business->id || $wrongUser || $trip->customer_phone !== $customer['phone']) {
            throw ValidationException::withMessages(['client_reference' => 'This transport reference is already in use.']);
        }
    }
}
