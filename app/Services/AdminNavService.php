<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ClaimRequest;
use App\Models\FeatureFlag;
use App\Models\ImportItem;
use App\Models\Order;
use App\Models\Report;
use App\Models\User;

class AdminNavService
{
    public const DEPARTMENTS = ['directory', 'shopping', 'booking', 'taxi'];

    public const SUPPORT_DEPARTMENT = 'support';

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
        return auth()->user() instanceof User && auth()->user()->canManagePlatform();
    }

    /**
     * The department the signed-in staff member is scoped to, or null for full access.
     */
    public function userDepartment(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->adminDepartment() : null;
    }

    public function departmentLabel(?string $department): string
    {
        return match ($department) {
            'directory' => 'Directory',
            'shopping' => 'Shopping',
            'booking' => 'Booking',
            'taxi' => 'Taxi / Transport',
            self::SUPPORT_DEPARTMENT => 'Support',
            default => 'Full Access',
        };
    }

    public function departmentOptions(): array
    {
        return [
            null => 'Full Access (all departments)',
            'directory' => 'Directory Department',
            'shopping' => 'Shopping Department',
            'booking' => 'Booking Department',
            'taxi' => 'Taxi / Transport Department',
            self::SUPPORT_DEPARTMENT => 'Support (customers + reviews only)',
        ];
    }

    /**
     * A menu group/item is visible when:
     *  - 'shared'  => every admin sees it
     *  - array     => full-access staff (null department) or a matching department sees it
     *  - null/absent => full-access staff only
     */
    private function visibleTo(array|string|null $tag, ?string $department): bool
    {
        if ($tag === 'shared') {
            return true;
        }

        if ($department === null) {
            return true;
        }

        if (is_array($tag)) {
            return in_array($department, $tag, true);
        }

        return false;
    }

    public function menuItems(): array
    {
        $badges = $this->badges();
        $isPower = $this->isPowerUser();
        $department = $this->userDepartment();

        $menu = [
            [
                'title' => 'Dashboard',
                'departments' => 'shared',
                'items' => [
                    ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => 'dashboard'],
                ],
            ],
            [
                'title' => 'Directory & Listings',
                'departments' => ['directory', self::SUPPORT_DEPARTMENT],
                'items' => [
                    ['label' => 'All Businesses', 'route' => 'admin.businesses', 'icon' => 'businesses', 'departments' => ['directory']],
                    ['label' => 'Pending Claims', 'route' => 'admin.claims*', 'icon' => 'shield', 'badge' => $badges['pending_claims'] ?? 0, 'departments' => ['directory']],
                    ['label' => 'Categories', 'route' => 'admin.category-tree*', 'icon' => 'categories', 'departments' => ['directory']],
                    ['label' => 'Reviews', 'route' => 'admin.reviews*', 'icon' => 'star', 'departments' => ['directory', self::SUPPORT_DEPARTMENT]],
                    ['label' => 'Photo Gallery', 'route' => 'admin.gallery*', 'icon' => 'content', 'departments' => ['directory', self::SUPPORT_DEPARTMENT]],
                ],
            ],
            [
                'title' => 'Sales & Customers',
                'departments' => 'shared',
                'items' => [
                    ['label' => 'Universal Orders', 'route' => 'admin.orders.universal*', 'icon' => 'orders', 'departments' => []],
                    ['label' => 'Transactions', 'route' => 'admin.transactions*', 'icon' => 'transactions', 'departments' => []],
                    ['label' => 'Customers', 'route' => 'admin.users*', 'icon' => 'users'],
                    ['label' => 'Business Owners', 'route' => 'admin.vendors*', 'icon' => 'sellers'],
                    ...($isPower ? [['label' => 'Staff & Roles', 'route' => 'admin.staff*', 'icon' => 'roles', 'departments' => []]] : []),
                ],
            ],
            [
                'title' => 'Commerce (Shopping)',
                'feature' => 'world.shop',
                'departments' => ['shopping'],
                'items' => [
                    ['label' => 'Products', 'route' => 'admin.products*', 'icon' => 'catalog'],
                    ['label' => 'Orders', 'route' => 'admin.orders*', 'icon' => 'orders', 'badge' => $badges['pending_orders'] ?? 0],
                    ['label' => 'Product Categories', 'route' => 'admin.product-categories*', 'icon' => 'categories'],
                    ['label' => 'Shop Sections', 'route' => 'admin.shop-sections*', 'icon' => 'sections'],
                    ['label' => 'Delivery Zones', 'route' => 'admin.areas*', 'icon' => 'areas'],
                ],
            ],
            [
                'title' => 'Bookings',
                'feature' => 'world.book',
                'departments' => ['booking'],
                'items' => [
                    ['label' => 'Services', 'route' => 'admin.services*', 'icon' => 'services'],
                    ['label' => 'All Bookings', 'route' => 'admin.bookings*', 'icon' => 'calendar', 'badge' => $badges['pending_bookings'] ?? 0],
                ],
            ],
            [
                'title' => 'Transport',
                'feature' => 'world.ride',
                'departments' => ['taxi'],
                'items' => [
                    ['label' => 'Transport Bookings', 'route' => 'admin.transport-bookings*', 'icon' => 'truck', 'badge' => $badges['pending_orders'] ?? 0],
                    ['label' => 'Vehicle Types', 'route' => 'admin.vehicle-types*', 'icon' => 'truck'],
                    ['label' => 'Transport Routes', 'route' => 'admin.transport-routes*', 'icon' => 'pincodes'],
                ],
            ],
            [
                'title' => 'Analytics',
                'departments' => self::DEPARTMENTS,
                'items' => [
                    ['label' => 'Overview', 'route' => 'admin.analytics*', 'icon' => 'analytics'],
                    ['label' => 'Booking Analytics', 'route' => 'admin.booking-analytics*', 'icon' => 'analytics'],
                    ['label' => 'Search Insights', 'route' => 'admin.search-history*', 'icon' => 'search'],
                    ['label' => 'Reports', 'route' => 'admin.reports*', 'icon' => 'reports', 'badge' => $badges['pending_reports'] ?? 0],
                ],
            ],
            [
                'title' => 'Communications',
                'departments' => 'shared',
                'items' => [
                    ['label' => 'Message Center', 'route' => 'admin.message-center*', 'icon' => 'comm'],
                    ['label' => 'Serviceable Areas', 'route' => 'admin.pincodes*', 'icon' => 'pincodes'],
                ],
            ],
        ];

        // Platform (power users only)
        if ($isPower && $department === null) {
            $menu[] = [
                'title' => 'Monetization',
                'items' => [
                    ['label' => 'Subscription Plans', 'route' => 'admin.subscription-plans*', 'icon' => 'plans'],
                    ['label' => 'Platform Earnings', 'route' => 'admin.earnings', 'icon' => 'earnings'],
                ],
            ];

            $menu[] = [
                'title' => 'Settings & Branding',
                'items' => [
                    ['label' => 'Business Modules', 'route' => 'admin.feature-flags*', 'icon' => 'flag', 'badge' => $badges['enabled_flags'] ?? 0, 'badge_suffix' => ' ON'],
                    ['label' => 'General Settings', 'route' => 'admin.settings', 'url' => route('admin.settings').'#general', 'icon' => 'settings'],
                    ['label' => 'Payments & Gateways', 'route' => 'admin.settings', 'url' => route('admin.settings').'#payment', 'icon' => 'payments'],
                ],
            ];

            $menu[] = [
                'title' => 'System',
                'items' => [
                    ['label' => 'Activity Logs', 'route' => 'admin.activity-logs*', 'icon' => 'logs'],
                ],
            ];

            $menu[] = [
                'title' => 'AI & Automation',
                'items' => [
                    ['label' => 'AI Import Queue', 'route' => 'admin.import*', 'icon' => 'import', 'badge' => $badges['pending_imports'] ?? 0],
                    ['label' => 'Import by Link', 'route' => 'admin.import.by-link', 'icon' => 'import'],
                    ['label' => 'Autopilot', 'route' => 'admin.autopilot', 'icon' => 'autopilot'],
                    ['label' => 'Agent Settings', 'route' => 'admin.agents*', 'icon' => 'agents'],
                ],
            ];
        }

        $launchControl = app(LaunchControlService::class);

        return collect($menu)
            ->filter(function (array $group) use ($department, $launchControl) {
                if (isset($group['feature'])) {
                    $slug = str_replace('world.', '', (string) $group['feature']);

                    if (! $launchControl->worldEnabled($slug)) {
                        return false;
                    }
                }

                return $this->visibleTo($group['departments'] ?? null, $department);
            })
            ->map(function (array $group) use ($department) {
                $group['items'] = collect($group['items'])
                    ->filter(fn (array $item) => $this->visibleTo($item['departments'] ?? $group['departments'] ?? null, $department))
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => count($group['items']) > 0)
            ->values()
            ->all();
    }
}
