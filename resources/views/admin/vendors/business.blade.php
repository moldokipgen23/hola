@extends('layouts.admin')

@section('title', $business->name)
@section('header', 'Business Owner Details')

@section('content')
<div class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.vendors') }}" class="hover:text-white">Business Owners</a>
    <span class="mx-2">›</span>
    <span class="text-white">{{ $business->name }}</span>
</div>

@php
    $typeBadge = match ($typeLabel) {
        'Shopping' => 'badge-green',
        'Booking' => 'badge-blue',
        'Taxi' => 'badge-yellow',
        default => 'bg-slate-500/20 text-slate-400',
    };
    $isSuspended = $business->createdBy && $business->createdBy->banned_at;
@endphp

<div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold">
            {{ strtoupper(substr($business->name, 0, 1)) }}
        </div>
        <div>
            <h2 class="text-xl font-bold text-white">{{ $business->name }}</h2>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span>
                @if($business->verification_status === 'verified')
                    <span class="badge badge-green">Verified</span>
                @elseif($business->verification_status === 'rejected')
                    <span class="badge badge-red">Rejected</span>
                @else
                    <span class="badge badge-yellow">Pending</span>
                @endif
            </div>
        </div>
    </div>
    <a href="{{ route('admin.vendors') }}" class="btn-ghost">Back to List</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main column -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Owner Info -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Owner Info</h3>
            @if($business->createdBy)
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-bold">
                            {{ strtoupper(substr($business->createdBy->name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-white font-medium">{{ $business->createdBy->name }}</p>
                            <p class="text-slate-500 text-xs">{{ $business->createdBy->email ?? $business->createdBy->phone }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.vendors.show', $business->created_by) }}" class="btn-ghost">Owner Page</a>
                        <a href="{{ route('admin.users.edit', $business->created_by) }}" class="btn-ghost">Edit Owner</a>
                        @if($isSuspended)
                            <form method="POST" action="{{ route('admin.users.unban', $business->created_by) }}">
                                @csrf
                                <button type="submit" class="btn-primary">Unsuspend</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.users.ban', $business->created_by) }}" data-confirm="Suspend this owner?">
                                @csrf
                                <button type="submit" class="btn-danger">Suspend</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-slate-500 text-xs">Phone</p>
                        <p class="text-white text-sm">{{ $business->createdBy->phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-xs">Joined</p>
                        <p class="text-white text-sm">{{ $business->createdBy->created_at->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-xs">Status</p>
                        @if($isSuspended)
                            <span class="badge badge-red">Suspended</span>
                        @else
                            <span class="badge badge-green">Active</span>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-slate-500 text-sm">No owner assigned.</p>
            @endif
        </div>

        <!-- Business Info -->
        <div class="glass-card p-6 rounded-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-semibold text-sm uppercase tracking-wider text-slate-400">Business Info</h3>
                <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn-ghost">Edit Business</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-slate-500 text-xs">Category</p>
                    <p class="text-white text-sm">{{ $business->category->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Type</p>
                    <p class="text-white text-sm">{{ $typeLabel }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Address</p>
                    <p class="text-white text-sm">{{ $business->address ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Phone</p>
                    <p class="text-white text-sm">{{ $business->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Email</p>
                    <p class="text-white text-sm">{{ $business->email ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Active Since</p>
                    <p class="text-white text-sm">{{ $business->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>

        <!-- Enabled Modules -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Enabled Modules</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <p class="text-slate-400 text-sm">Shopping</p>
                    <p class="text-white font-semibold mt-1">
                        {{ $modules['shopping'] ? 'Yes' : 'No' }}
                        @if($modules['shopping'])
                            <span class="text-slate-500 text-xs font-normal">· {{ $counts['products'] }} products</span>
                        @endif
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <p class="text-slate-400 text-sm">Booking</p>
                    <p class="text-white font-semibold mt-1">
                        {{ $modules['booking'] ? 'Yes' : 'No' }}
                        @if($modules['booking'])
                            <span class="text-slate-500 text-xs font-normal">· {{ $counts['services'] }} services</span>
                        @endif
                    </p>
                </div>
                <div class="p-4 rounded-xl bg-white/[0.02] border border-white/5">
                    <p class="text-slate-400 text-sm">Taxi</p>
                    <p class="text-white font-semibold mt-1">
                        {{ $modules['taxi'] ? 'Yes' : 'No' }}
                        @if($modules['taxi'])
                            <span class="text-slate-500 text-xs font-normal">· {{ $counts['vehicles'] }} vehicles</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats (Last 30 Days) -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Stats · Last 30 Days</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <p class="text-slate-500 text-xs">Orders</p>
                    <p class="text-white font-semibold text-lg">{{ $stats['orders'] }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Bookings</p>
                    <p class="text-white font-semibold text-lg">{{ $stats['bookings'] }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Revenue</p>
                    <p class="text-white font-semibold text-lg">₹{{ number_format($stats['revenue'], 0) }}</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Commission</p>
                    <p class="text-white font-semibold text-lg text-slate-600">—</p>
                </div>
                <div>
                    <p class="text-slate-500 text-xs">Rating</p>
                    <p class="text-white font-semibold text-lg">
                        @if($stats['reviews_count'])
                            {{ number_format($stats['rating'], 1) }} ⭐ <span class="text-slate-500 text-xs font-normal">({{ $stats['reviews_count'] }})</span>
                        @else
                            <span class="text-slate-600">—</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Actions -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Actions</h3>
            <div class="flex flex-col gap-2">
                <a href="{{ route('admin.businesses.show', $business->id) }}" class="btn-primary w-full text-center">View Business</a>
                <a href="{{ route('admin.businesses.edit', $business->id) }}" class="btn-ghost w-full text-center">Edit Business</a>
                @if($business->createdBy)
                    <a href="{{ route('admin.users.edit', $business->created_by) }}" class="btn-ghost w-full text-center">Edit Owner</a>
                    @if($isSuspended)
                        <form method="POST" action="{{ route('admin.users.unban', $business->created_by) }}" class="w-full">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Unsuspend Owner</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.ban', $business->created_by) }}" data-confirm="Suspend this owner?" class="w-full">
                            @csrf
                            <button type="submit" class="btn-danger w-full">Suspend Owner</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Recent Orders</h3>
            @forelse($recentOrders as $order)
                <div class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                    <div>
                        <p class="text-white text-sm">{{ $order->order_number }}</p>
                        <p class="text-slate-500 text-xs">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-white text-sm">₹{{ number_format($order->total, 2) }}</p>
                        <p class="text-slate-500 text-xs">{{ ucfirst($order->status) }}</p>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No orders yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
