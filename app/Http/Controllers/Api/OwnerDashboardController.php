<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $businesses = Business::where('created_by', $request->user()->id)
            ->withCount(['reviews', 'reports', 'claimRequests'])
            ->get();

        $totalViews = $businesses->sum('views_count');
        $totalCalls = $businesses->sum('call_count');
        $totalWhatsApps = $businesses->sum('whatsapp_count');
        $totalDirections = $businesses->sum('directions_count');

        $recentConversations = \App\Models\Conversation::whereIn('business_id', $businesses->pluck('id'))
            ->with(['user:id,name', 'business:id,name'])
            ->latest('last_message_at')
            ->take(5)
            ->get();

        return response()->json([
            'businesses' => $businesses,
            'stats' => compact('totalViews', 'totalCalls', 'totalWhatsApps', 'totalDirections'),
            'recentConversations' => $recentConversations,
        ]);
    }

    public function businesses(Request $request)
    {
        $businesses = Business::where('created_by', $request->user()->id)
            ->withCount(['reviews', 'reports'])
            ->with('category')
            ->latest()
            ->get();

        return response()->json(compact('businesses'));
    }
}
