<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview()
    {
        $user = Auth::user();
        $businessIds = Business::where('created_by', $user->id)->pluck('id');

        $business = null;
        if ($businessIds->isNotEmpty()) {
            $business = Business::where('created_by', $user->id)->first();
        }

        $stats = [
            'views' => Business::whereIn('id', $businessIds)->sum('views_count'),
            'orders' => Order::whereIn('business_id', $businessIds)->count(),
            'revenue' => Order::whereIn('business_id', $businessIds)
                ->where('status', 'delivered')
                ->sum('total'),
            'bookings' => \App\Models\Booking::whereIn('business_id', $businessIds)->count(),
        ];

        $popularProducts = Product::whereIn('business_id', $businessIds)
            ->with('business:id,name')
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        $recentOrders = Order::whereIn('business_id', $businessIds)
            ->with('business:id,name', 'items')
            ->latest()
            ->limit(20)
            ->get();

        return view('vendor.analytics.index', compact('stats', 'popularProducts', 'recentOrders', 'businesses'));
    }

    public function popularProducts()
    {
        $user = Auth::user();
        $businessIds = Business::where('created_by', $user->id)->pluck('id');

        $products = Product::whereIn('business_id', $businessIds)
            ->with('business:id,name')
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return view('vendor.analytics.index', compact('products'));
    }

    public function revenueChart()
    {
        $user = Auth::user();
        $businessIds = Business::where('created_by', $user->id)->pluck('id');

        $chartData = Order::whereIn('business_id', $businessIds)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $chartData]);
    }
}
