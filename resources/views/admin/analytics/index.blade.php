@extends('layouts.admin')

@section('title', 'Analytics')
@section('header', 'Analytics')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Total Views</h3>
        <p class="text-3xl font-bold mt-2">{{ number_format($analytics['total_views'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Total Saves</h3>
        <p class="text-3xl font-bold mt-2 text-blue-500">{{ number_format($analytics['total_saves'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Call Clicks</h3>
        <p class="text-3xl font-bold mt-2 text-green-500">{{ number_format($analytics['total_calls'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">WhatsApp Clicks</h3>
        <p class="text-3xl font-bold mt-2 text-emerald-500">{{ number_format($analytics['total_whatsapps'] ?? 0) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Directions Clicks</h3>
        <p class="text-3xl font-bold mt-2 text-indigo-500">{{ number_format($analytics['total_directions'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Shares</h3>
        <p class="text-3xl font-bold mt-2 text-purple-500">{{ number_format($analytics['total_shares'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Total Products</h3>
        <p class="text-3xl font-bold mt-2">{{ $analytics['total_products'] ?? 0 }}</p>
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-sm font-medium text-slate-400">Pending Reports</h3>
        <p class="text-3xl font-bold mt-2 text-red-500">{{ $analytics['pending_reports'] ?? 0 }}</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-white font-semibold mb-4">Top Businesses by Views</h3>
        @forelse($analytics['top_businesses'] ?? [] as $business)
            <div class="flex justify-between items-center py-2 border-b border-white/5">
                <span>{{ $business->name }}</span>
                <span class="text-sm text-slate-400">{{ number_format($business->views_count) }} views</span>
            </div>
        @empty
            <p class="text-slate-400">No data yet.</p>
        @endforelse
    </div>
    <div class="glass-card p-6 rounded-lg">
        <h3 class="text-white font-semibold mb-4">Recent Reports</h3>
        @forelse($analytics['recent_reports'] ?? [] as $report)
            <div class="flex justify-between items-center py-2 border-b border-white/5">
                <span>{{ $report->type }} - {{ $report->business->name ?? 'N/A' }}</span>
                <span class="badge
                    {{ $report->status === 'pending' ? 'badge-yellow' : '' }}
                    {{ $report->status === 'resolved' ? 'badge-green' : '' }}
                    ">{{ $report->status }}</span>
            </div>
        @empty
            <p class="text-slate-400">No reports yet.</p>
        @endforelse
    </div>
</div>
@endsection
