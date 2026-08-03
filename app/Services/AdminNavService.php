<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ClaimRequest;
use App\Models\FeatureFlag;
use App\Models\ImportItem;
use App\Models\Order;
use App\Models\Report;

class AdminNavService
{
    public function badges(): array
    {
        return [
            'pending_claims' => ClaimRequest::where('status', 'pending')->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'pending_imports' => ImportItem::where('status', 'pending')->count(),
            'enabled_flags' => FeatureFlag::where('is_enabled', true)->count(),
        ];
    }

    public function isPowerUser(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin', 'admin'], true);
    }
}
