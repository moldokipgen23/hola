<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripPlacementService;
use App\Services\TripWorkflowService;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function vehicles($slug)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();
        $vehicles = $business->vehicles()->where('is_active', true)->get();

        return response()->json(compact('vehicles'));
    }

    public function estimateFare(Request $request, $slug)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'distance_km' => 'required|numeric|min:0.5',
        ]);

        $vehicle = Vehicle::where('business_id', $business->id)
            ->where('is_active', true)
            ->where('id', $validated['vehicle_id'])
            ->firstOrFail();

        $fare = $vehicle->estimatedFare((float) $validated['distance_km']);

        return response()->json([
            'fare' => round($fare, 2),
            'base_fare' => $vehicle->base_fare,
            'fare_per_km' => $vehicle->fare_per_km,
            'distance_km' => $validated['distance_km'],
            'min_km' => $vehicle->min_km,
            'fare_status' => $vehicle->requires_quote ? 'quote_required' : 'estimated',
            'message' => $vehicle->requires_quote
                ? 'Contact the operator for a final quote.'
                : 'This is an estimate. Confirm the final fare directly with the operator.',
        ]);
    }

    public function bookTrip(Request $request, $slug, TripPlacementService $trips)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        if (! $business->hasTransportModule()) {
            return response()->json(['message' => 'Transport not available for this business.'], 422);
        }

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'pickup_location' => 'required|string|max:1000',
            'drop_location' => 'required|string|max:1000',
            'pickup_lat' => 'nullable|required_with:pickup_lng|numeric|between:-90,90',
            'pickup_lng' => 'nullable|required_with:pickup_lat|numeric|between:-180,180',
            'drop_lat' => 'nullable|required_with:drop_lng|numeric|between:-90,90',
            'drop_lng' => 'nullable|required_with:drop_lat|numeric|between:-180,180',
            'distance_km' => 'nullable|numeric|min:0.1|max:5000',
            'seats_required' => 'nullable|integer|min:1|max:50',
            'scheduled_at' => 'nullable|date',
            'return_at' => 'nullable|date|after:scheduled_at',
            'load_weight' => 'nullable|numeric|min:0.01|max:100000',
            'load_description' => 'nullable|string|max:2000',
            'client_reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = $trips->place(
            $business,
            (int) $validated['vehicle_id'],
            [
                'name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
            $validated,
            $request->user()?->id,
            $validated['client_reference'] ?? null,
        );

        return response()->json([
            'message' => $result['duplicate']
                ? 'This transport request was already received.'
                : 'Transport request sent. Confirm fare and payment directly with the operator.',
            'trip' => $result['trip'],
            'duplicate' => $result['duplicate'],
            'payment_mode' => 'offline',
            'business_contact' => ['phone' => $business->phone, 'whatsapp' => $business->whatsapp],
        ], $result['duplicate'] ? 200 : 201);
    }

    public function myTrips(Request $request)
    {
        return response()->json([
            'trips' => Trip::where('user_id', $request->user()->id)
                ->with(['business:id,name,slug,photos', 'vehicle:id,name,type,image'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function cancelTrip(Request $request, $id, TripWorkflowService $workflow)
    {
        $trip = Trip::findOrFail($id);

        if ($trip->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);
        $trip = $workflow->transition($trip, 'cancelled', $request->reason);

        return response()->json(['message' => 'Trip cancelled.', 'trip' => $trip]);
    }
}
