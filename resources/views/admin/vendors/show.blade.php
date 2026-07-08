@extends('layouts.admin')

@section('title', $vendor->name)
@section('header', 'Vendor Details')

@section('content')
<div class="text-sm text-slate-500 mb-4">
    <a href="{{ route('admin.vendors') }}" class="hover:text-white">Vendors</a>
    <span class="mx-2">›</span>
    <span class="text-white">{{ $vendor->name }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Vendor Info -->
        <div class="glass-card p-6 rounded-xl">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white text-xl font-bold">
                        {{ strtoupper(substr($vendor->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">{{ $vendor->name }}</h2>
                        <p class="text-slate-400 text-sm">{{ $vendor->email }} @if($vendor->phone) · {{ $vendor->phone }} @endif</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    @if($vendor->banned_at)
                        <form method="POST" action="{{ route('admin.users.unban', $vendor->id) }}">
                            @csrf
                            <button class="btn-ghost text-green-400">Unban</button>
                        </form>
                    @else
                        <a href="{{ route('admin.users.edit', $vendor->id) }}" class="btn-ghost">Edit</a>
                    @endif
                </div>
            </div>
            <div class="flex gap-4 mt-4">
                <span class="badge badge-blue">Vendor</span>
                @if($vendor->email_verified_at || $vendor->phone_verified_at)
                    <span class="badge badge-green">Verified</span>
                @else
                    <span class="badge badge-yellow">Unverified</span>
                @endif
                @if($vendor->banned_at)
                    <span class="badge badge-red">Banned</span>
                @elseif(!$vendor->is_active)
                    <span class="badge badge-yellow">Inactive</span>
                @endif
            </div>
        </div>

        <!-- Businesses -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4">Businesses ({{ $businesses->count() }})</h3>
            @forelse($businesses as $business)
                <div class="flex items-center justify-between p-4 rounded-xl bg-white/[0.02] border border-white/5 mb-3">
                    <div>
                        <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-white font-medium hover:text-blue-400 transition">{{ $business->name }}</a>
                        <div class="flex items-center gap-3 mt-1">
                            @if($business->category)
                                <span class="text-slate-500 text-xs">{{ $business->category->name }}</span>
                            @endif
                            <span class="text-slate-600 text-xs">{{ $business->address }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-slate-500 text-xs">{{ $business->views_count }} views</span>
                        @if($business->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-yellow">Inactive</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No businesses claimed yet.</p>
            @endforelse
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Stats -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Vendor Stats</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Total Businesses</span>
                    <span class="text-white font-medium">{{ $businesses->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Total Views</span>
                    <span class="text-white font-medium">{{ $businesses->sum('views_count') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Total Calls</span>
                    <span class="text-white font-medium">{{ $businesses->sum('call_count') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Total Reviews</span>
                    <span class="text-white font-medium">{{ $businesses->sum('review_count') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Last Login</span>
                    <span class="text-white font-medium">{{ $vendor->last_login_at ? $vendor->last_login_at->diffForHumans() : 'Never' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Login Count</span>
                    <span class="text-white font-medium">{{ $vendor->login_count }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400 text-sm">Member Since</span>
                    <span class="text-white font-medium">{{ $vendor->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider text-slate-400">Recent Activity</h3>
            <div class="space-y-2">
                @forelse($recentActivity as $activity)
                    <div class="text-sm py-1">
                        <span class="text-slate-400">{{ $activity->created_at->diffForHumans() }}</span>
                        <span class="text-white"> &mdash; {{ ucwords(str_replace('_', ' ', $activity->action)) }}</span>
                    </div>
                @empty
                    <p class="text-slate-500 text-xs">No recent activity.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
