<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Vehicle;
use App\Models\VehicleRental;
use App\Services\VehicleRentalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Public vehicle hire / rental endpoints (backend only — Flutter wiring later).
 */
class VehicleRentalController extends Controller
{
    public function __construct(private readonly VehicleRentalService $rentals) {}

    /**
     * GET /transport/rentals/businesses — verified vendors offering vehicle hire.
     */
    public function businesses()
    {
        $businesses = Business::where('enabled_modules->transport', true)
            ->where('verification_status', 'verified')
            ->whereHas('vehicles', fn ($q) => $q->where('is_active', true)->where('service_mode', 'rental')->whereNotNull('price_per_day'))
            ->withCount(['vehicles' => fn ($q) => $q->where('service_mode', 'rental')->whereNotNull('price_per_day')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'phone', 'whatsapp']);

        return response()->json(['data' => $businesses]);
    }

    /**
     * GET /transport/rentals/businesses/{slug}?from=&to= — vehicles available for a date range.
     */
    public function vehicles($slug, Request $request)
    {
        $business = Business::where('slug', $slug)->where('verification_status', 'verified')->firstOrFail();
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : null;

        $vehicles = $this->rentals->rentalVehicles($business, $from, $to)
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'image' => $v->image,
                'seats' => $v->seats,
                'price_per_day' => (float) $v->price_per_day,
                'with_driver' => $v->service_mode === 'rental',
                'terms' => $v->terms,
                'available' => $v->rental_available,
                'conflicts' => $v->rental_conflicts,
            ])
            ->values();

        return response()->json(['data' => $vehicles]);
    }

    /**
     * POST /transport/rentals/vehicles/{id}/book — book a vehicle for dates.
     */
    public function book(Request $request, $id)
    {
        $vehicle = Vehicle::with('business')->where('id', $id)->where('is_active', true)->firstOrFail();
        abort_unless($this->rentals->canOfferRental($vehicle->business), 404, 'This vehicle is not available for online hire.');

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'with_driver' => 'nullable|boolean',
            'terms_accepted' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $rental = $this->rentals->book(
                $vehicle,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                [
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'email' => $validated['customer_email'] ?? null,
                ],
                $request->boolean('with_driver'),
                $request->boolean('terms_accepted'),
                $request->user()?->id,
                $validated['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'message' => 'Hire requested. Confirm with the operator to lock it in.',
            'rental' => $rental->load(['vehicle', 'business:id,name,slug,phone,whatsapp']),
            'payment_mode' => 'offline',
            'business_contact' => ['phone' => $vehicle->business->phone, 'whatsapp' => $vehicle->business->whatsapp],
        ], 201);
    }

    /**
     * GET /transport/rentals/my — the signed-in customer's hire bookings.
     */
    public function my(Request $request)
    {
        return response()->json([
            'data' => VehicleRental::with(['vehicle', 'business:id,name,slug'])
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }
}
