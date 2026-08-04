@extends('vendor.layouts.dashboard')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<!-- Welcome -->
<div class="glass-card p-6 mb-8">
    <h3 class="text-xl font-bold text-white">Welcome back, {{ Auth::user()->name ?? 'Vendor' }}!</h3>
    <p class="text-slate-400 text-sm mt-1">Here's what's happening with your businesses today.</p>
</div>

@php
    $needsSetup = $businesses->contains(fn ($b) => $b->enabled_modules === null);
    $pendingVerification = $businesses->first(fn ($b) => $b->verification_status === 'pending');
@endphp

@if($pendingVerification)
<div class="bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/30 rounded-xl p-5 mb-8">
    <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-amber-500/30 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M12 3l9 16H3l9-16z"/></svg>
        </div>
        <div class="flex-1">
            <h4 class="text-white font-semibold">{{ $pendingVerification->name }} is pending verification</h4>
            <p class="text-slate-400 text-sm mt-1">Your business is listed in the directory now. Once an admin verifies it, you'll be able to choose what you offer and enable your storefront, bookings and features.</p>
        </div>
    </div>
</div>
@endif

@if($needsSetup)
<div class="bg-gradient-to-r from-purple-500/20 to-blue-500/20 border border-purple-500/30 rounded-xl p-5 mb-8">
    <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-purple-500/30 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div class="flex-1">
            <h4 class="text-white font-semibold">Finish setting up your business</h4>
            <p class="text-slate-400 text-sm mt-1">Tell us what type of business you run and we'll set up the right features for you.</p>
            <a href="{{ route('vendor.setup.redirect') }}" class="inline-block mt-3 btn-primary text-sm px-4 py-2">Set Up Now</a>
        </div>
    </div>
</div>
@endif

@if($vendorSetup ?? null)
<div class="glass-card p-6 mb-8">
    <div class="flex items-center justify-between gap-4 mb-4">
        <div>
            <h3 class="text-white font-semibold">Readiness checklist</h3>
            <p class="text-slate-400 text-sm mt-1">
                @php $nextStep = $vendorSetup->nextStep(); @endphp
                @if($nextStep)
                    Next step: <span class="text-purple-400 font-medium">{{ $nextStep }}</span>
                @else
                    Everything done — your listing is ready!
                @endif
            </p>
        </div>
        <div class="text-right shrink-0">
            <p class="text-3xl font-bold text-white">{{ $vendorSetup->calculateCompletionPercentage() }}%</p>
            <p class="text-xs text-slate-500">complete</p>
        </div>
    </div>
    <div class="h-2 bg-white/10 rounded-full overflow-hidden mb-5">
        <div class="h-full bg-gradient-to-r from-purple-500 to-blue-500 transition-all" style="width: {{ $vendorSetup->calculateCompletionPercentage() }}%"></div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach($vendorSetup->checklist as $item)
            <div class="flex items-center gap-2 text-sm {{ $item['done'] ? 'text-slate-400' : 'text-slate-500' }}">
                @if($item['done'])
                    <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                @else
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
                <span class="{{ $item['done'] ? '' : 'font-medium' }}">{{ $item['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>
@endif

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="stat-card">
        <div class="stat-icon bg-purple-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Total Businesses</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['businesses'] ?? 0 }}</p>
    </div>

    @if($hasBookings ?? false)
    <div class="stat-card">
        <div class="stat-icon bg-blue-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Active Bookings</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['active_bookings'] ?? 0 }}</p>
    </div>
    @endif
    @if($hasOrders ?? false)
    <div class="stat-card">
        <div class="stat-icon bg-amber-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Pending Orders</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['pending_orders'] ?? 0 }}</p>
    </div>
    @endif
    @if($hasProducts ?? false)
    <div class="stat-card">
        <div class="stat-icon bg-green-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Total Products</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['products'] ?? 0 }}</p>
    </div>
    @endif
</div>

<!-- Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
    @if($hasBookings ?? false)
    <!-- Recent Bookings -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold">Recent Bookings</h3>
            @if($defaultBusinessId)<a href="{{ route('vendor.bookings', $defaultBusinessId) }}" class="text-purple-400 text-sm font-medium hover:text-purple-300 transition">View all</a>@endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentBookings ?? [] as $booking)
                    <tr>
                        <td class="text-sm">{{ $booking->customer_name }}</td>
                        <td class="text-sm">{{ $booking->service->name ?? '-' }}</td>
                        <td class="text-sm text-slate-400">{{ $booking->booking_date->format('M d, Y') }}</td>
                        <td>
                            @php
                                $colors = ['pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'completed' => 'badge-green', 'cancelled' => 'badge-red'];
                            @endphp
                            <span class="badge {{ $colors[$booking->status] ?? 'badge-yellow' }}">{{ ucfirst($booking->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500 py-6">No bookings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    @if($hasOrders ?? false)
    <!-- Recent Orders -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold">Recent Orders</h3>
            @if($defaultBusinessId)<a href="{{ route('vendor.orders', $defaultBusinessId) }}" class="text-purple-400 text-sm font-medium hover:text-purple-300 transition">View all</a>@endif
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order#</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders ?? [] as $order)
                    <tr>
                        <td class="text-sm font-medium text-white">{{ $order->order_number }}</td>
                        <td class="text-sm">{{ $order->customer_name }}</td>
                        <td class="text-sm">${{ number_format($order->total, 2) }}</td>
                        <td>
                            @php
                                $statusLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
                                $statusClasses = ['pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'preparing' => 'bg-purple-500/20 text-purple-400', 'ready' => 'bg-cyan-500/20 text-cyan-400', 'delivered' => 'badge-green', 'cancelled' => 'badge-red'];
                            @endphp
                            <span class="badge {{ $statusClasses[$order->status] ?? 'badge-yellow' }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500 py-6">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>

<!-- Quick Actions -->
<div class="glass-card p-5">
    <h3 class="text-white font-semibold mb-4">Quick Actions</h3>
    <div class="flex flex-wrap gap-3">
        @if($hasOrders && $defaultBusinessId)
        <a href="{{ route('vendor.products.create', $defaultBusinessId) }}" class="btn-primary">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 inline mr-1.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Product
        </a>
        @endif
        @if($hasBookings ?? false)
        <a href="{{ route('vendor.bookings', $defaultBusinessId) }}" class="btn-ghost">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 inline mr-1.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            View Bookings
        </a>
        @endif
        @if($hasOrders ?? false)
        <a href="{{ route('vendor.orders', $defaultBusinessId) }}" class="btn-ghost">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 inline mr-1.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Manage Orders
        </a>
        @endif
    </div>
</div>
@endsection
