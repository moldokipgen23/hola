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

        $usersItems = [
            ['label' => 'Customers', 'route' => 'admin.users*', 'icon' => 'users'],
            ['label' => 'Business Owners', 'route' => 'admin.vendors*', 'icon' => 'sellers'],
        ];

        if ($isPower) {
            $usersItems[] = ['label' => 'Staff', 'route' => 'admin.staff*', 'icon' => 'staff'];
        }

        $menu = [
            [
                'title' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
                ],
            ],
            [
                'title' => 'Directory',
                'items' => [
                    ['label' => 'All Businesses', 'route' => 'admin.businesses', 'icon' => 'businesses'],
                    ['label' => 'Pending Claims', 'route' => 'admin.claims*', 'icon' => 'shield', 'badge' => $badges['pending_claims'] ?? 0],
                    ['label' => 'AI Import Queue', 'route' => 'admin.import*', 'icon' => 'import', 'badge' => $badges['pending_imports'] ?? 0],
                    ['label' => 'Categories', 'route' => 'admin.category-tree*', 'icon' => 'categories'],
                    ['label' => 'Reviews', 'route' => 'admin.reviews*', 'icon' => 'star'],
                ],
            ],
            [
                'title' => 'Orders',
                'feature' => 'world.shop',
                'items' => [
                    ['label' => 'All Orders', 'route' => 'admin.orders*', 'icon' => 'orders', 'badge' => $badges['pending_orders'] ?? 0],
                ],
            ],
            [
                'title' => 'Catalog',
                'feature' => 'world.shop',
                'items' => [
                    ['label' => 'Products', 'route' => 'admin.products*', 'icon' => 'products'],
                    ['label' => 'Product Categories', 'route' => 'admin.product-categories*', 'icon' => 'categories'],
                    ['label' => 'Shop Sections', 'route' => 'admin.shop-sections*', 'icon' => 'sections'],
                    ['label' => 'Services', 'route' => 'admin.services*', 'icon' => 'services', 'feature' => 'world.book'],
                ],
            ],
            ['title' => 'Users', 'items' => $usersItems],
            [
                'title' => 'Fulfillment',
                'items' => [
                    ['label' => 'Delivery Areas', 'route' => 'admin.areas*', 'icon' => 'areas'],
                    ['label' => 'Pincodes', 'route' => 'admin.pincodes*', 'icon' => 'pincodes'],
                    ['label' => 'Vehicle Types', 'route' => 'admin.vehicle-types*', 'icon' => 'truck', 'feature' => 'world.ride'],
                ],
            ],
            [
                'title' => 'Analytics',
                'items' => [
                    ['label' => 'Overview', 'route' => 'admin.analytics*', 'icon' => 'analytics'],
                    ['label' => 'Search Insights', 'route' => 'admin.search-history*', 'icon' => 'search'],
                    ['label' => 'Reports', 'route' => 'admin.reports*', 'icon' => 'reports', 'badge' => $badges['pending_reports'] ?? 0],
                ],
            ],
            [
                'title' => 'Settings',
                'items' => [
                    ['label' => 'Launch Controls', 'route' => 'admin.feature-flags*', 'icon' => 'flag', 'badge' => $badges['enabled_flags'] ?? 0, 'badge_suffix' => ' ON'],
                    ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings'],
                    ['label' => 'Capability Presets', 'route' => 'admin.capability-templates*', 'icon' => 'presets'],
                ],
            ],
        ];

        $systemItems = [
            ['label' => 'Transactions', 'route' => 'admin.transactions*', 'icon' => 'transactions'],
        ];

        if ($isPower) {
            $systemItems[] = ['label' => 'Activity Logs', 'route' => 'admin.activity-logs*', 'icon' => 'logs'];
            $systemItems[] = ['label' => 'API Keys', 'route' => 'admin.integration-keys*', 'icon' => 'keys'];
        }

        $menu[] = ['title' => 'System', 'items' => $systemItems];

        if ($isPower) {
            $menu[] = [
                'title' => 'AI Agents',
                'items' => [
                    ['label' => 'Autopilot', 'route' => 'admin.autopilot', 'icon' => 'autopilot'],
                    ['label' => 'Agent Settings', 'route' => 'admin.agents*', 'icon' => 'agents'],
                ],
            ];
        }

        return $menu;
    }
}
