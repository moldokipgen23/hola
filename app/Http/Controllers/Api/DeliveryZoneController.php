<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Pincode;
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
        if ($slug) {
            $business = Business::active()->where('slug', $slug)->firstOrFail();
        } elseif ($request->business_id) {
            $business = Business::active()->findOrFail($request->business_id);
        } else {
            return response()->json(['message' => 'Provide business_id or slug.'], 422);
        }

        $subtotal = $request->float('subtotal', 0);

        // ─── Serviceability gate ───
        // Check if the business's own area is serviceable
        if ($business->pincode && ! Pincode::isServiceable($business->pincode)) {
            $pincodeInfo = Pincode::lookup($business->pincode);
            $areaName = $pincodeInfo ? "{$pincodeInfo->district}, {$pincodeInfo->state}" : 'this area';

            return response()->json([
                'available' => false,
                'deliverable' => false,
                'message' => "Business is currently not accepting orders in {$areaName}.",
            ]);
        }

        // Check if customer's pincode is serviceable (when provided)
        if ($request->pincode) {
            $customerPincode = Pincode::lookup($request->pincode);
            if ($customerPincode && ! $customerPincode->serviceable) {
                return response()->json([
                    'available' => false,
                    'deliverable' => false,
                    'message' => "We're coming to {$customerPincode->district}, {$customerPincode->state} soon!",
                    'pincode_info' => [
                        'pincode' => $customerPincode->pincode,
                        'locality' => $customerPincode->locality,
                        'district' => $customerPincode->district,
                        'state' => $customerPincode->state,
                    ],
                ]);
            }
        }

        // ─── Radius-based check (like Swiggy/Zepto) ───
        if ($request->latitude && $request->longitude) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;
            $radius = (float) ($business->delivery_radius_km ?? 5);

            if (! $business->latitude || ! $business->longitude) {
                return response()->json([
                    'available' => false,
                    'deliverable' => false,
                    'message' => 'This business has not set its location.',
                ]);
            }

            $distance = 6371 * acos(
                cos(deg2rad($lat)) * cos(deg2rad($business->latitude)) *
                cos(deg2rad($business->longitude) - deg2rad($lng)) +
                sin(deg2rad($lat)) * sin(deg2rad($business->latitude))
            );

            $withinRadius = $distance <= $radius;

            // Pincode info for context (optional)
            $pincodeInfo = null;
            if ($request->pincode) {
                $p = Pincode::where('pincode', $request->pincode)->first();
                if ($p) {
                    $pincodeInfo = [
                        'pincode' => $p->pincode,
                        'locality' => $p->locality,
                        'district' => $p->district,
                        'state' => $p->state,
                    ];
                }
            }

            // Also check delivery zones for fee/eta
            $zone = null;
            if ($request->pincode) {
                $zone = $business->deliveryZones()
                    ->where('is_active', true)
                    ->whereJsonContains('pincodes', $request->pincode)
                    ->first();
            }
            if (! $zone) {
                $zone = $business->deliveryZones()->where('is_active', true)->first();
            }

            return response()->json([
                'available' => $withinRadius,
                'deliverable' => $withinRadius,
                'distance_km' => round($distance, 2),
                'radius_km' => $radius,
                'delivery_fee' => $zone ? (float) $zone->delivery_fee : 0,
                'estimated_minutes' => $zone?->estimated_minutes,
                'min_order_amount' => $zone?->min_order_amount ? (float) $zone->min_order_amount : null,
                'above_minimum' => ! $zone || $zone->min_order_amount === null || $subtotal >= $zone->min_order_amount,
                'message' => $withinRadius
                    ? 'Delivery available to your location.'
                    : 'Your location is outside this business\'s delivery area.',
                'pincode_info' => $pincodeInfo,
            ]);
        }

        // ─── Legacy: area_id / pincode fallback ───
        $pincodeInfo = null;
        if ($request->pincode) {
            $pincodeInfo = Pincode::where('pincode', $request->pincode)->first();
        }

        $zoneQuery = $business->deliveryZones()->where('is_active', true);

        if ($request->pincode) {
            $p = $request->pincode;
            $zoneQuery->where(function ($q) use ($p) {
                $q->whereJsonContains('pincodes', $p);
            });
        } elseif ($request->area_id) {
            $request->validate(['area_id' => 'required|exists:areas,id']);
            $zoneQuery->where('area_id', $request->area_id);
        } else {
            return response()->json(['message' => 'Provide latitude/longitude, area_id, or pincode.'], 422);
        }

        $zone = $zoneQuery->first();

        if (! $zone) {
            return response()->json([
                'available' => false,
                'deliverable' => false,
                'message' => 'We do not deliver to this area/pincode.',
                'pincode_info' => $pincodeInfo ? [
                    'pincode' => $pincodeInfo->pincode,
                    'locality' => $pincodeInfo->locality,
                    'district' => $pincodeInfo->district,
                    'state' => $pincodeInfo->state,
                ] : null,
            ]);
        }

        $aboveMinimum = $zone->min_order_amount === null || $subtotal >= $zone->min_order_amount;

        return response()->json([
            'available' => true,
            'deliverable' => true,
            'above_minimum' => $aboveMinimum,
            'delivery_fee' => (float) $zone->delivery_fee,
            'estimated_minutes' => $zone->estimated_minutes,
            'min_order_amount' => $zone->min_order_amount ? (float) $zone->min_order_amount : null,
            'message' => $aboveMinimum ? 'Delivery available to this area.' : "Minimum order of ₹{$zone->min_order_amount} required.",
            'pincode_info' => $pincodeInfo ? [
                'pincode' => $pincodeInfo->pincode,
                'locality' => $pincodeInfo->locality,
                'district' => $pincodeInfo->district,
                'state' => $pincodeInfo->state,
            ] : null,
        ]);
    }
}
