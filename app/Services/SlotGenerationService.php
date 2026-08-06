<?php

namespace App\Services;

use App\Models\AvailabilityRule;
use App\Models\BookableResource;
use App\Models\Booking;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SlotGenerationService
{
    /**
     * Generate concrete bookable slots for a service on a given date.
     *
     * Prefers rule-driven generation (BookableResource + AvailabilityRule).
     * Falls back to the classic weekly TimeSlot records when no rules exist.
     */
    public function slotsFor(Service $service, CarbonInterface $date, int $partySize = 1, int $reservationUnits = 1): array
    {
        $resources = $service->resources()->active()->with('availabilityRules')->orderBy('sort_order')->get();

        if ($resources->isEmpty()) {
            return $this->fromWeeklySlots($service, $date, $partySize, $reservationUnits);
        }

        $slots = [];
        foreach ($resources as $resource) {
            foreach ($this->slotsForResource($service, $resource, $date, $partySize, $reservationUnits) as $slot) {
                $slots[] = $slot;
            }
        }

        usort($slots, fn ($a, $b) => $a['start_time'] <=> $b['start_time']);

        return $slots;
    }

    /**
     * Generate slots for one resource from its availability rules.
     */
    public function slotsForResource(
        Service $service,
        BookableResource $resource,
        CarbonInterface $date,
        int $partySize = 1,
        int $reservationUnits = 1,
    ): array {
        $slots = [];

        foreach ($resource->availabilityRules as $rule) {
            if (! $rule->is_active) {
                continue;
            }
            if ($this->isBlackedOut($rule, $date)) {
                continue;
            }
            if ($this->isBeforeWindow($rule, $date)) {
                continue;
            }

            $dayMatches = $rule->day_of_week === null || (int) $rule->day_of_week === $date->dayOfWeek;
            if (! $dayMatches) {
                continue;
            }

            $start = Carbon::parse($date->toDateString().' '.$rule->start_time);
            $end = Carbon::parse($date->toDateString().' '.$rule->end_time);
            if ($end->lte($start)) {
                continue;
            }

            $duration = (int) ($rule->slot_duration_minutes ?? $service->duration ?? 60);
            $buffer = (int) ($rule->buffer_minutes ?? 0);
            $capacity = (int) ($rule->capacity ?? $resource->capacity ?? 1);

            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $slotStart = $start->copy();
                $slotEnd = $start->copy()->addMinutes($duration);

                if (! $this->isPastSlot($service, $slotStart, $rule)) {
                    $available = $this->availableForResource($resource, $date, $slotStart, $slotEnd, $capacity);
                    $requested = $service->booking_mode === 'slot' ? $reservationUnits : $partySize;

                    $slots[] = [
                        'resource_id' => $resource->id,
                        'resource_name' => $resource->name,
                        'start_time' => $slotStart->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                        'duration_minutes' => $duration,
                        'capacity' => $capacity,
                        'available' => $available,
                        'can_accommodate' => $available >= $requested,
                        'price' => $service->price,
                        'price_unit' => $service->price_unit,
                    ];
                }

                $start = $start->addMinutes($duration + $buffer);
            }
        }

        return $slots;
    }

    private function fromWeeklySlots(
        Service $service,
        CarbonInterface $date,
        int $partySize,
        int $reservationUnits,
    ): array {
        return $service->timeSlots()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('day_of_week')->orWhere('day_of_week', $date->dayOfWeek))
            ->orderBy('start_time')
            ->get()
            ->map(function ($slot) use ($date, $partySize, $reservationUnits, $service) {
                $available = $slot->availableSlots($date->toDateString());
                $isPast = Carbon::parse($date->toDateString().' '.$slot->start_time)->isPast();
                $requested = $service->booking_mode === 'slot' ? $reservationUnits : $partySize;

                return [
                    'time_slot_id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'capacity' => $slot->capacity,
                    'available' => $available,
                    'can_accommodate' => ! $isPast && $available >= $requested,
                    'price' => $slot->price_override ?? $service->price,
                    'price_unit' => $service->price_unit,
                ];
            })
            ->filter(fn ($slot) => $slot['can_accommodate'])
            ->values()
            ->all();
    }

    private function availableForResource(
        BookableResource $resource,
        CarbonInterface $date,
        Carbon $slotStart,
        Carbon $slotEnd,
        int $capacity,
    ): int {
        $column = $resource->service?->booking_mode === 'slot' ? 'reservation_units' : 'party_size';

        $booked = Booking::where('service_id', $resource->service_id)
            ->whereDate('booking_date', $date->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('start_time', '<', $slotEnd->format('H:i'))
            ->where('end_time', '>', $slotStart->format('H:i'))
            ->where(function ($q) use ($resource) {
                $q->where('resource_id', $resource->id)
                    ->orWhereNull('resource_id');
            })
            ->sum($column);

        return max(0, $capacity - $booked);
    }

    private function isBlackedOut(AvailabilityRule $rule, CarbonInterface $date): bool
    {
        return in_array($date->toDateString(), $rule->blackout_dates ?? [], true);
    }

    private function isBeforeWindow(AvailabilityRule $rule, CarbonInterface $date): bool
    {
        $window = (int) ($rule->booking_window_days ?? 30);

        return $date->gt(today()->addDays($window));
    }

    private function isPastSlot(Service $service, Carbon $slotStart, AvailabilityRule $rule): bool
    {
        if (! $slotStart->isToday()) {
            return false;
        }

        $notice = (int) ($rule->minimum_notice_hours ?? 0);
        if ($notice > 0 && $slotStart->lt(now()->addHours($notice))) {
            return true;
        }

        return $slotStart->isPast();
    }
}
