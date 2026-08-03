<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimeSlotController extends Controller
{
    public function slotsByService(Request $request, $serviceId)
    {
        $validated = $request->validate([
            'date' => 'nullable|date|after_or_equal:today',
            'party_size' => 'nullable|integer|min:1|max:100',
            'reservation_units' => 'nullable|integer|min:1|max:100',
        ]);
        $date = Carbon::parse($validated['date'] ?? now()->toDateString());
        $partySize = (int) ($validated['party_size'] ?? 1);
        $reservationUnits = (int) ($validated['reservation_units'] ?? 1);
        $service = Service::where('is_active', true)
            ->whereHas('business', fn ($query) => $query->active()->inServiceableArea()->ofModule('bookings'))
            ->with('business')
            ->findOrFail($serviceId);

        $advanceDays = $service->advance_booking_days ?? 60;
        if ($date->startOfDay()->gt(today()->addDays($advanceDays))) {
            return response()->json([
                'message' => "This service accepts bookings up to {$advanceDays} days ahead.",
                'slots' => [],
            ], 422);
        }

        $slots = $service->timeSlots()
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('day_of_week')->orWhere('day_of_week', $date->dayOfWeek);
            })
            ->orderBy('start_time')
            ->get()
            ->map(function ($slot) use ($date, $partySize, $reservationUnits, $service) {
                $available = $slot->availableSlots($date->toDateString());
                $isPast = Carbon::parse($date->toDateString().' '.$slot->start_time)->isPast();
                $requested = $service->booking_mode === 'slot' ? $reservationUnits : $partySize;
                $slot->available = $available;
                $slot->can_accommodate = ! $isPast && $available >= $requested;
                $slot->price = $slot->price_override ?? $slot->service->price;

                return $slot;
            })
            ->filter(fn ($slot) => $slot->can_accommodate)
            ->values();

        return response()->json(compact('slots'));
    }
}
