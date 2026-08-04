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

    public function menuItems(): array
    {
        $badges = $this->badges();
        $isPower = $this->isPowerUser();

        $menu = [
            [
                'title' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
                ],
            ],
            [
                'title' => 'Directory + AI Scout',
                'items' => [
                    ['label' => 'All Businesses', 'route' => 'admin.businesses', 'icon' => 'businesses'],
                    ['label' => 'Featured', 'route' => 'admin.featured*', 'icon' => 'star'],
                    ['label' => 'Claims', 'route' => 'admin.claims*', 'icon' => 'shield', 'badge' => $badges['pending_claims'] ?? 0],
                    ['label' => 'AI Imports', 'route' => 'admin.import*', 'icon' => 'import', 'badge' => $badges['pending_imports'] ?? 0],
                    ['label' => 'Directory Categories', 'route' => 'admin.category-tree*', 'icon' => 'categories'],
                    ['label' => 'Classification Audit', 'route' => 'admin.classification-audit*', 'icon' => 'audit'],
                ],
            ],
            [
                'title' => 'Business Types',
                'items' => [
                    ['label' => 'Shopping', 'route' => 'admin.businesses-type.shopping', 'icon' => 'shopping', 'feature' => 'world.shop'],
                    ['label' => 'Booking', 'route' => 'admin.businesses-type.booking', 'icon' => 'calendar', 'feature' => 'world.book'],
                    ['label' => 'Taxi / Transport', 'route' => 'admin.businesses-type.taxi', 'icon' => 'truck', 'feature' => 'world.ride'],
                ],
            ],
            [
                'title' => 'Sellers',
                'items' => [
                    ['label' => 'All Sellers', 'route' => 'admin.vendors*', 'icon' => 'sellers'],
                ],
            ],
            [
                'title' => 'Shop',
                'feature' => 'world.shop',
                'items' => [
                    ['label' => 'Products', 'route' => 'admin.products*', 'icon' => 'products'],
                    ['label' => 'Orders', 'route' => 'admin.orders*', 'icon' => 'orders', 'badge' => $badges['pending_orders'] ?? 0],
                    ['label' => 'Shop Sections', 'route' => 'admin.shop-sections*', 'icon' => 'sections'],
                    ['label' => 'Product Categories', 'route' => 'admin.product-categories*', 'icon' => 'categories'],
                ],
            ],
            [
                'title' => 'Book',
                'feature' => 'world.book',
                'items' => [
                    ['label' => 'Bookings', 'route' => 'admin.bookings*', 'icon' => 'bookings', 'badge' => $badges['pending_bookings'] ?? 0],
                    ['label' => 'Services', 'route' => 'admin.services*', 'icon' => 'services'],
                ],
            ],
            [
                'title' => 'Fulfillment',
                'items' => [
                    ['label' => 'Areas', 'route' => 'admin.areas*', 'icon' => 'areas'],
                    ['label' => 'Pincodes', 'route' => 'admin.pincodes*', 'icon' => 'pincodes'],
                ],
            ],
            [
                'title' => 'Customers',
                'items' => [
                    ['label' => 'Users', 'route' => 'admin.users*', 'icon' => 'users'],
                    ['label' => 'Reviews', 'route' => 'admin.reviews*', 'icon' => 'star'],
                    ['label' => 'Reports', 'route' => 'admin.reports*', 'icon' => 'reports', 'badge' => $badges['pending_reports'] ?? 0],
                ],
            ],
            [
                'title' => 'Content',
                'items' => [
                    ['label' => 'Homepage', 'route' => 'admin.homepage*', 'icon' => 'homepage'],
                    ['label' => 'Coming-soon Interest', 'route' => 'admin.area-interests*', 'icon' => 'interest'],
                    ['label' => 'Analytics', 'route' => 'admin.analytics*', 'icon' => 'analytics'],
                    ['label' => 'Search History', 'route' => 'admin.search-history*', 'icon' => 'search'],
                ],
            ],
        ];

        if ($isPower) {
            $menu[] = [
                'title' => 'Team',
                'items' => [
                    ['label' => 'Staff', 'route' => 'admin.staff*', 'icon' => 'staff'],
                ],
            ];
        }

        $menu[] = [
            'title' => 'System',
            'items' => [
                ['label' => 'Launch Controls', 'route' => 'admin.feature-flags*', 'icon' => 'flag', 'badge' => $badges['enabled_flags'] ?? 0, 'badge_suffix' => ' ON'],
                ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings'],
                ['label' => 'Capability Presets', 'route' => 'admin.capability-templates*', 'icon' => 'presets'],
                ['label' => 'Transactions', 'route' => 'admin.transactions*', 'icon' => 'transactions'],
            ],
        ];

        if ($isPower) {
            $menu[] = [
                'title' => 'Developer',
                'items' => [
                    ['label' => 'API Keys', 'route' => 'admin.integration-keys*', 'icon' => 'keys'],
                    ['label' => 'Activity Logs', 'route' => 'admin.activity-logs*', 'icon' => 'logs'],
                    ['label' => 'Autopilot', 'route' => 'admin.autopilot', 'icon' => 'autopilot'],
                    ['label' => 'Agent Settings', 'route' => 'admin.agents*', 'icon' => 'agents'],
                ],
            ];
        }

        return $menu;
    }
}
