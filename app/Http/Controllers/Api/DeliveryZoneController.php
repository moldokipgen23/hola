<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\DeliveryEligibilityService;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index($slug)
    {
        $business = Business::active()->where('slug', $slug)->firstOrFail();
        $zones = $business->deliveryZones()
            ->with('area:id,name,slug')
            ->where('is_active', true)
            ->get();

        return response()->json(compact('zones'));
    }

    public function checkEligibility(Request $request, $slug = null)
    {
        $validated = $request->validate([
            'business_id' => 'nullable|integer|exists:businesses,id',
            'pincode' => 'nullable|digits:6',
            'area_id' => 'nullable|integer|exists:areas,id',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        if (! $slug && empty($validated['business_id'])) {
            return response()->json(['message' => 'Provide business_id or slug.'], 422);
        }

        $business = $slug
            ? Business::active()->where('slug', $slug)->firstOrFail()
            : Business::active()->findOrFail($validated['business_id']);

        $result = app(DeliveryEligibilityService::class)->check(
            $business,
            $validated['pincode'] ?? null,
            isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            isset($validated['area_id']) ? (int) $validated['area_id'] : null,
            (float) ($validated['subtotal'] ?? 0),
        );

        return response()->json($result);
    }
}
