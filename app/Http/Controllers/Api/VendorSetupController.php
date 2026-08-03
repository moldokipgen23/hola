<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSetupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->currentBusiness;

        if (!$business) {
            return response()->json(['message' => 'No active business'], 404);
        }

        $setup = VendorSetup::firstOrCreate(
            ['business_id' => $business->id],
            ['business_id' => $business->id]
        );

        return response()->json([
            'setup' => $setup,
            'percentage' => $setup->calculateCompletionPercentage(),
            'checklist' => $setup->checklist,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $request->user()->currentBusiness;

        if (!$business) {
            return response()->json(['message' => 'No active business'], 404);
        }

        $validated = $request->validate([
            'profile_complete' => 'sometimes|boolean',
            'products_added' => 'sometimes|boolean',
            'photos_uploaded' => 'sometimes|boolean',
            'operating_hours_set' => 'sometimes|boolean',
            'delivery_configured' => 'sometimes|boolean',
            'first_order_received' => 'sometimes|boolean',
            'first_booking_received' => 'sometimes|boolean',
        ]);

        $setup = VendorSetup::firstOrCreate(
            ['business_id' => $business->id],
            ['business_id' => $business->id]
        );

        $setup->update($validated);
        $setup->markCompleteIfReady();

        return response()->json([
            'setup' => $setup,
            'percentage' => $setup->calculateCompletionPercentage(),
            'checklist' => $setup->checklist,
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        $business = $request->user()->currentBusiness;

        if (!$business) {
            return response()->json(['message' => 'No active business'], 404);
        }

        $setup = VendorSetup::firstOrCreate(
            ['business_id' => $business->id],
            ['business_id' => $business->id]
        );

        return response()->json([
            'percentage' => $setup->calculateCompletionPercentage(),
            'checklist' => $setup->checklist,
            'completed' => $setup->completed_at !== null,
        ]);
    }
}
