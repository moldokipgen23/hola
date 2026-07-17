<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function vehicles($slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();
        $vehicles = $business->vehicles()->where('is_active', true)->get();

        return response()->json(compact('vehicles'));
    }

    public function estimateFare(Request $request, $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'distance_km' => 'required|numeric|min:0.5',
        ]);

        $vehicle = Vehicle::where('business_id', $business->id)
            ->where('id', $validated['vehicle_id'])
            ->firstOrFail();

        $fare = $vehicle->estimatedFare((float) $validated['distance_km']);

        return response()->json([
            'fare' => round($fare, 2),
            'base_fare' => $vehicle->base_fare,
            'fare_per_km' => $vehicle->fare_per_km,
            'distance_km' => $validated['distance_km'],
            'min_km' => $vehicle->min_km,
        ]);
    }

    public function bookTrip(Request $request, $slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();

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
            'pickup_lat' => 'nullable|numeric',
            'pickup_lng' => 'nullable|numeric',
            'drop_lat' => 'nullable|numeric',
            'drop_lng' => 'nullable|numeric',
            'distance_km' => 'nullable|numeric|min:0',
            'seats_required' => 'nullable|integer|min:1|max:50',
            'trip_date' => 'nullable|date',
            'trip_time' => 'nullable',
            'notes' => 'nullable|string|max:1000',
        ]);

        $vehicle = Vehicle::where('business_id', $business->id)
            ->where('id', $validated['vehicle_id'])
            ->firstOrFail();

        $distance = $validated['distance_km'] ?? 1;
        $fare = $vehicle->estimatedFare((float) $distance);

        $tripData = [
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'pickup_location' => $validated['pickup_location'],
            'drop_location' => $validated['drop_location'],
            'pickup_lat' => $validated['pickup_lat'] ?? null,
            'pickup_lng' => $validated['pickup_lng'] ?? null,
            'drop_lat' => $validated['drop_lat'] ?? null,
            'drop_lng' => $validated['drop_lng'] ?? null,
            'distance_km' => $distance,
            'fare' => round($fare, 2),
            'seats_required' => $validated['seats_required'] ?? 1,
            'trip_date' => $validated['trip_date'] ?? now()->toDateString(),
            'trip_time' => $validated['trip_time'] ?? null,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->user()) {
            $tripData['user_id'] = $request->user()->id;
        }

        $trip = Trip::create($tripData);
        $trip->load('vehicle');

        return response()->json([
            'message' => 'Trip booked successfully.',
            'trip' => $trip,
        ], 201);
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

    public function cancelTrip(Request $request, $id)
    {
        $trip = Trip::findOrFail($id);

        if ($trip->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! in_array($trip->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Trip cannot be cancelled at this stage.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $trip->markCancelled($request->reason);

        return response()->json(['message' => 'Trip cancelled.', 'trip' => $trip]);
    }
}
