<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DeliveryConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeliveryConfigController extends Controller
{
    public function show(int $business)
    {
        $config = DeliveryConfig::where('business_id', $business)->first();

        if (! $config) {
            return response()->json(['message' => 'No delivery config found.'], 404);
        }

        return response()->json(['data' => $config]);
    }

    public function update(Request $request, int $business)
    {
        $businessModel = Business::findOrFail($business);
        Gate::authorize('update', $businessModel);

        $validated = $request->validate([
            'delivery_radius_km' => 'nullable|numeric|min:0|max:100',
            'min_order_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'free_delivery_above' => 'nullable|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:5|max:180',
            'zones' => 'nullable|array',
            'zones.*.name' => 'required|string|max:255',
            'zones.*.fee' => 'required|numeric|min:0',
            'zones.*.radius' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $config = DeliveryConfig::updateOrCreate(
            ['business_id' => $businessModel->id],
            $validated
        );

        return response()->json(['data' => $config]);
    }
}
