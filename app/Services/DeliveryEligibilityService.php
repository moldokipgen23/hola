<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DeliveryZone;
use App\Models\Pincode;

class DeliveryEligibilityService
{
    public function check(
        Business $business,
        ?string $customerPincode = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $areaId = null,
        float $subtotal = 0,
    ): array {
        if (! $this->businessIsTransactionServiceable($business)) {
            return $this->unavailable('This business is listed, but transactions are not enabled in its area yet.');
        }

        $pincode = $customerPincode ? Pincode::lookup($customerPincode) : null;
        if ($customerPincode && ! $pincode) {
            return $this->unavailable('Enter a valid Indian pincode.');
        }
        if ($pincode && ! $pincode->serviceable) {
            return $this->unavailable(
                "Transactions are not available in {$pincode->district}, {$pincode->state} yet.",
                $this->pincodeInfo($pincode),
            );
        }

        $zone = $this->matchingZone($business, $customerPincode, $areaId);
        $distance = null;
        $radius = (float) ($business->delivery_radius_km ?? 5);
        $withinRadius = false;

        if ($latitude !== null && $longitude !== null) {
            if ($business->latitude === null || $business->longitude === null) {
                return $this->unavailable('This business has not set a delivery origin.');
            }

            $distance = $this->distanceKm(
                (float) $business->latitude,
                (float) $business->longitude,
                $latitude,
                $longitude,
            );
            $withinRadius = $distance <= $radius;
        }

        $covered = $zone !== null || $withinRadius;
        $minimum = $zone?->min_order_amount !== null ? (float) $zone->min_order_amount : null;
        $aboveMinimum = $minimum === null || $subtotal >= $minimum;
        $deliverable = $covered && $aboveMinimum;

        $message = match (true) {
            ! $covered && $distance !== null => 'Your location is outside this business’s delivery area.',
            ! $covered => 'This business has not configured delivery for that pincode or area.',
            ! $aboveMinimum => "Minimum order of ₹{$minimum} required.",
            default => 'Delivery is available. Payment will be handled offline/COD.',
        };

        return [
            'available' => $covered,
            'deliverable' => $deliverable,
            'transaction_serviceable' => true,
            'above_minimum' => $aboveMinimum,
            'distance_km' => $distance !== null ? round($distance, 2) : null,
            'radius_km' => $distance !== null ? $radius : null,
            'delivery_fee' => (float) ($zone?->delivery_fee ?? 0),
            'estimated_minutes' => $zone?->estimated_minutes,
            'min_order_amount' => $minimum,
            'matched_by' => $zone ? ($customerPincode ? 'pincode_zone' : 'area_zone') : ($withinRadius ? 'radius' : null),
            'message' => $message,
            'pincode_info' => $pincode ? $this->pincodeInfo($pincode) : null,
        ];
    }

    public function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $cosine = cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude))
            * cos(deg2rad($toLongitude) - deg2rad($fromLongitude))
            + sin(deg2rad($fromLatitude)) * sin(deg2rad($toLatitude));

        return 6371 * acos(max(-1, min(1, $cosine)));
    }

    private function businessIsTransactionServiceable(Business $business): bool
    {
        if ($business->pincode) {
            return Pincode::isServiceable($business->pincode);
        }

        return $business->district
            && Pincode::where('district', $business->district)->where('serviceable', true)->exists();
    }

    private function matchingZone(Business $business, ?string $pincode, ?int $areaId): ?DeliveryZone
    {
        $query = $business->deliveryZones()->where('is_active', true);

        if ($pincode) {
            return $query->whereJsonContains('pincodes', $pincode)->first();
        }

        if ($areaId) {
            return $query->where('area_id', $areaId)->first();
        }

        return null;
    }

    private function unavailable(string $message, ?array $pincodeInfo = null): array
    {
        return [
            'available' => false,
            'deliverable' => false,
            'transaction_serviceable' => false,
            'above_minimum' => false,
            'message' => $message,
            'pincode_info' => $pincodeInfo,
        ];
    }

    private function pincodeInfo(Pincode $pincode): array
    {
        return [
            'pincode' => $pincode->pincode,
            'locality' => $pincode->locality,
            'district' => $pincode->district,
            'state' => $pincode->state,
        ];
    }
}
