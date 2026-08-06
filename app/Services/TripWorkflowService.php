<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripWorkflowService
{
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['started', 'cancelled'],
        'started' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transition(Trip $trip, string $status, ?string $reason = null, array $driver = []): Trip
    {
        return DB::transaction(function () use ($driver, $reason, $status, $trip) {
            $locked = Trip::lockForUpdate()->findOrFail($trip->id);
            if (! in_array($status, self::TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from '{$locked->status}' to '{$status}'.",
                ]);
            }

            $updates = ['status' => $status];
            if ($status === 'confirmed') {
                $updates['booked_at'] = now();
                $updates['driver_name'] = $driver['name'] ?? $locked->driver_name;
                $updates['driver_phone'] = $driver['phone'] ?? $locked->driver_phone;
            } elseif ($status === 'started') {
                $updates['started_at'] = now();
            } elseif ($status === 'completed') {
                $updates['completed_at'] = now();
            } elseif ($status === 'cancelled') {
                $updates['cancelled_at'] = now();
                $updates['cancellation_reason'] = $reason;
            }
            $locked->update($updates);

            // Record platform commission when a trip completes.
            if ($status === 'completed' && $locked->business) {
                try {
                    app(MonetizationService::class)->recordCommission(
                        $locked->business,
                        Trip::class,
                        $locked->id,
                        (float) ($locked->fare ?? 0),
                    );
                } catch (\Throwable $e) {
                    // Commission must never fail the trip workflow.
                }
            }

            NotificationService::tripStatusChanged($locked, $status);

            $vehicle = $locked->vehicle()->lockForUpdate()->first();
            if ($vehicle && $vehicle->availability_status !== 'offline') {
                if ($status === 'started') {
                    $vehicle->update(['availability_status' => 'busy']);
                } elseif (in_array($status, ['completed', 'cancelled'], true)) {
                    $hasStartedTrip = Trip::where('vehicle_id', $vehicle->id)
                        ->where('id', '!=', $locked->id)
                        ->where('status', 'started')
                        ->exists();
                    if (! $hasStartedTrip) {
                        $vehicle->update(['availability_status' => 'available', 'next_available_at' => null]);
                    }
                }
            }

            return $locked->fresh()->load('vehicle');
        });
    }

    public function quote(Trip $trip, float $fare, ?string $notes = null): Trip
    {
        return DB::transaction(function () use ($fare, $notes, $trip) {
            $locked = Trip::lockForUpdate()->findOrFail($trip->id);
            if (! in_array($locked->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['fare' => 'This request can no longer be quoted.']);
            }
            $locked->update(['fare' => $fare, 'fare_status' => 'quoted', 'quote_notes' => $notes]);

            return $locked->fresh()->load('vehicle');
        });
    }

    public function markCashCollected(Trip $trip): Trip
    {
        return DB::transaction(function () use ($trip) {
            $locked = Trip::lockForUpdate()->findOrFail($trip->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['payment_status' => 'A cancelled trip cannot be marked as paid.']);
            }
            $locked->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

            return $locked->fresh();
        });
    }
}
