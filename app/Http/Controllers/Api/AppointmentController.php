<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\SlotGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Business-scoped booking endpoints for the appointment flow.
 *
 * The mobile app books by business, so it needs slots/availability keyed on
 * the business (not only per-service). These delegate to the existing
 * SlotGenerationService. Staff is not modelled yet — always empty so the app
 * can gracefully fall back to "any available staff".
 */
class AppointmentController extends Controller
{
    public function slots(Request $request, string $slug)
    {
        $validated = $request->validate([
            'date' => 'nullable|date|after_or_equal:today',
            'service_id' => 'nullable|integer|exists:services,id',
        ]);

        $business = Business::active()->where('slug', $slug)->firstOrFail();
        $date = Carbon::parse($validated['date'] ?? now()->toDateString());

        $services = $business->services()
            ->where('is_active', true)
            ->when($validated['service_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($services->isEmpty()) {
            return response()->json(['slots' => [], 'generated' => true]);
        }

        $advanceDays = $services->max('advance_booking_days') ?? 60;
        if ($date->startOfDay()->gt(today()->addDays($advanceDays))) {
            return response()->json([
                'message' => "This business accepts bookings up to {$advanceDays} days ahead.",
                'slots' => [],
            ], 422);
        }

        $generator = app(SlotGenerationService::class);
        $slots = [];
        foreach ($services as $service) {
            foreach ($generator->slotsFor($service, $date, 1, 1) as $slot) {
                $slot['service_id'] = $service->id;
                $slot['service_name'] = $service->name;
                $slots[] = $slot;
            }
        }

        // Deduplicate by start_time; prefer an available slot for that time.
        $byTime = [];
        foreach ($slots as $slot) {
            $time = $slot['start_time'] ?? '';
            if (! isset($byTime[$time]) || ($slot['available'] ?? false)) {
                $byTime[$time] = $slot;
            }
        }

        return response()->json(['slots' => array_values($byTime), 'generated' => true]);
    }

    public function availability(Request $request, string $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();
        $services = $business->services()->where('is_active', true)->get();

        if ($services->isEmpty()) {
            return response()->json(['available_dates' => []]);
        }

        $advanceDays = min((int) ($services->max('advance_booking_days') ?? 60), 90);
        $dates = [];
        for ($i = 0; $i < $advanceDays; $i++) {
            $dates[] = today()->addDays($i)->toDateString();
        }

        return response()->json(['available_dates' => $dates]);
    }

    public function staff(string $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        // Staff assignment is not modelled yet; the app falls back to
        // "any available staff" when this list is empty.
        return response()->json(['staff' => []]);
    }
}
