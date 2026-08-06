<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\SlotGenerationService;
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

        $slots = app(SlotGenerationService::class)
            ->slotsFor($service, $date, $partySize, $reservationUnits);

        return response()->json(['slots' => $slots, 'generated' => true]);
    }
}
