<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pincode;
use Illuminate\Http\Request;

class PincodeController extends Controller
{
    public function lookup(Request $request)
    {
        $request->validate(['pincode' => 'required|string|size:6']);

        $pincode = Pincode::where('pincode', $request->pincode)->first();

        if (! $pincode) {
            return response()->json(['found' => false, 'message' => 'Pincode not found in database.'], 404);
        }

        return response()->json([
            'found' => true,
            'pincode' => $pincode->pincode,
            'locality' => $pincode->locality,
            'district' => $pincode->district,
            'state' => $pincode->state,
            'latitude' => $pincode->latitude,
            'longitude' => $pincode->longitude,
            'serviceable' => $pincode->serviceable,
        ]);
    }

    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);

        $results = Pincode::search($request->q)
            ->where('serviceable', true)
            ->take(20)
            ->get()
            ->map(fn ($p) => [
                'pincode' => $p->pincode,
                'locality' => $p->locality,
                'district' => $p->district,
                'state' => $p->state,
            ]);

        return response()->json(['data' => $results]);
    }

    public function nearby(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        $radius = $request->float('radius', 10);

        $pincodes = Pincode::haversine($request->latitude, $request->longitude, $radius)->take(50)->get();

        return response()->json([
            'data' => $pincodes->map(fn ($p) => [
                'pincode' => $p->pincode,
                'locality' => $p->locality,
                'district' => $p->district,
                'state' => $p->state,
                'distance_km' => round((float) $p->distance, 2),
            ]),
        ]);
    }
}
