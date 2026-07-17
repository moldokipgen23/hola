<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SavedListing;
use Illuminate\Http\Request;

class SavedListingController extends Controller
{
    public function index(Request $request)
    {
        $saved = SavedListing::where('user_id', $request->user()->id)
            ->with(['business' => function ($query) {
                $query->with(['category', 'subcategory']);
            }])
            ->get();

        return response()->json([
            'saved' => $saved,
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
        ]);

        $existing = SavedListing::where('user_id', $request->user()->id)
            ->where('business_id', $request->business_id)
            ->first();

        if ($existing) {
            $existing->delete();
            Business::where('id', $request->business_id)->decrement('saves_count');

            return response()->json([
                'saved' => false,
                'message' => 'Removed from saved listings.',
            ]);
        }

        SavedListing::create([
            'user_id' => $request->user()->id,
            'business_id' => $request->business_id,
        ]);
        Business::where('id', $request->business_id)->increment('saves_count');

        return response()->json([
            'saved' => true,
            'message' => 'Added to saved listings.',
        ]);
    }

    public function check(Request $request)
    {
        $isSaved = SavedListing::where('user_id', $request->user()->id)
            ->where('business_id', $request->business_id)
            ->exists();

        return response()->json([
            'saved' => $isSaved,
        ]);
    }
}
