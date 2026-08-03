@extends('vendor.layouts.dashboard')

@section('title', 'Analytics')
@section('header', 'Analytics Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="stat-card">
        <div class="stat-icon bg-blue-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        </div>
        <p class="text-slate-400 text-sm">Total Views</p>
        <p class="text-white text-2xl font-bold mt-1">{{ number_format($stats['views'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-green-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </div>
        <p class="text-slate-400 text-sm">Total Orders</p>
        <p class="text-white text-2xl font-bold mt-1">{{ number_format($stats['orders'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-slate-400 text-sm">Revenue</p>
        <p class="text-white text-2xl font-bold mt-1">&#8377;{{ number_format($stats['revenue'] ?? 0, 2) }}</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-amber-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <p class="text-slate-400 text-sm">Bookings</p>
        <p class="text-white text-2xl font-bold mt-1">{{ number_format($stats['bookings'] ?? 0) }}</p>
    </div>
</div>

<!-- Popular Products -->
<div class="glass-card rounded-lg p-6 mb-6">
    <h3 class="text-white font-semibold mb-4">Popular Products</h3>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Business</th>
                    <th>Views</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($popularProducts as $product)
                    <tr>
                        <td class="font-medium text-sm">{{ $product->name }}</td>
                        <td class="text-sm text-slate-400">{{ $product->business->name ?? '-' }}</td>
                        <td class="text-sm">{{ number_format($product->views_count ?? 0) }}</td>
                        <td class="text-sm">&#8377;{{ number_format($product->price ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-400">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Orders -->
<div class="glass-card rounded-lg p-6 mb-6">
    <h3 class="text-white font-semibold mb-4">Recent Orders</h3>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Business</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td class="font-medium text-sm">{{ $order->order_number }}</td>
                        <td class="text-sm">{{ $order->customer_name ?? '-' }}</td>
                        <td class="text-sm text-slate-400">{{ $order->business->name ?? '-' }}</td>
                        <td class="text-sm">&#8377;{{ number_format($order->total ?? 0, 2) }}</td>
                        <td>
                            @php
                                $statusColors = [
                                    'pending' => 'badge-yellow',
                                    'confirmed' => 'badge-blue',
                                    'preparing' => 'badge-blue',
                                    'ready' => 'badge-blue',
                                    'out_for_delivery' => 'badge-blue',
                                    'delivered' => 'badge-green',
                                    'cancelled' => 'badge-red',
                                    'rejected' => 'badge-red',
                                ];
                            @endphp
                            <span class="badge {{ $statusColors[$order->status] ?? 'badge-yellow' }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                        </td>
                        <td class="text-sm text-slate-400">{{ $order->created_at->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Revenue Chart Placeholder -->
<div class="glass-card rounded-lg p-6">
    <h3 class="text-white font-semibold mb-4">Revenue (Last 30 Days)</h3>
    <div class="h-64 flex items-center justify-center text-slate-500">
        <p>Revenue chart will be displayed here.</p>
    </div>
</div>

@endsection
