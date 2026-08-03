@extends('layouts.admin')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="stat-card">
        <div class="stat-icon bg-blue-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Total Businesses</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['businesses'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-slate-500">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
            All listings
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-green-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Active Businesses</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['active_businesses'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-green-500">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
            Live now
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-purple-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Categories</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['categories'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-slate-500">
            Active categories
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-amber-500/10">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-6 h-6 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">Total Users</p>
        <p class="text-3xl font-bold text-white mt-1">{{ $stats['users'] ?? 0 }}</p>
        <div class="mt-3 flex items-center gap-1 text-xs text-slate-500">
            Registered users
        </div>
    </div>
</div>

<!-- Tables -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Recent Businesses -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold">Recent Businesses</h3>
            <a href="{{ route('admin.businesses') }}" class="text-blue-400 text-sm font-medium hover:text-blue-300 transition">View all</a>
        </div>
        <div class="space-y-1">
            @forelse($recentBusinesses ?? [] as $business)
                <div class="flex items-center justify-between py-3 border-b border-white/5 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center text-white text-xs font-bold">
                            {{ strtoupper(substr($business->name, 0, 2)) }}
                        </div>
                        <div>
                            <p class="text-white text-sm font-medium">{{ $business->name }}</p>
                            <p class="text-slate-500 text-xs">{{ $business->category->name ?? 'Uncategorized' }}</p>
                        </div>
                    </div>
                    <span class="text-slate-500 text-xs">{{ $business->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-slate-500 text-sm">No businesses yet</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Pending Claims -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold">Pending Claims</h3>
            <a href="{{ route('admin.claims') }}" class="text-blue-400 text-sm font-medium hover:text-blue-300 transition">View all</a>
        </div>
        <div class="space-y-1">
            @forelse($pendingClaims ?? [] as $claim)
                <div class="flex items-center justify-between py-3 border-b border-white/5 last:border-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-amber-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        </div>
                        <div>
                            <p class="text-white text-sm font-medium">{{ $claim->user->name }}</p>
                            <p class="text-slate-500 text-xs">wants {{ $claim->business->name }}</p>
                        </div>
                    </div>
                    <span class="badge badge-yellow">Pending</span>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-slate-500 text-sm">No pending claims</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
