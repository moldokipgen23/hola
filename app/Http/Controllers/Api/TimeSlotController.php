<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;

class TimeSlotController extends Controller
{
    public function slotsByService($serviceId)
    {
        $service = Service::with('business')->findOrFail($serviceId);
        $date = request('date', now()->toDateString());

        $slots = $service->timeSlots()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get()
            ->map(function ($slot) use ($date) {
                $available = $slot->availableSlots($date);
                $slot->available = $available;
                $slot->price = $slot->price_override ?? $slot->service->price;

                return $slot;
            });

        return response()->json(compact('slots'));
    }
}
