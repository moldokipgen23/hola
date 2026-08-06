<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::active()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'cities' => $cities->map(fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'state' => $city->state,
                'district' => $city->district,
                'pincode' => $city->pincode,
                'is_home' => $city->is_home,
                'businesses_count' => $city->businesses()->active()->count(),
            ]),
        ]);
    }

    public function show(string $slug)
    {
        $city = City::active()->where('slug', $slug)->firstOrFail();

        return response()->json([
            'city' => $city,
        ]);
    }
}
