@extends('layouts.admin')

@section('title', 'Analytics')
@section('header', 'Analytics Dashboard')

@section('content')
<!-- Summary Stats -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Total Views</p>
        <p class="text-2xl font-bold mt-1">{{ number_format($analytics['total_views'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Calls</p>
        <p class="text-2xl font-bold mt-1 text-green-400">{{ number_format($analytics['total_calls'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">WhatsApp</p>
        <p class="text-2xl font-bold mt-1 text-emerald-400">{{ number_format($analytics['total_whatsapps'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Directions</p>
        <p class="text-2xl font-bold mt-1 text-indigo-400">{{ number_format($analytics['total_directions'] ?? 0) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Shares</p>
        <p class="text-2xl font-bold mt-1 text-purple-400">{{ number_format($analytics['total_shares'] ?? 0) }}</p>
    </div>
</div>

<!-- Platform Stats -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-white">{{ $analytics['total_businesses'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Businesses</p>
    </div>
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-blue-400">{{ $analytics['total_users'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Users</p>
    </div>
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-yellow-400">{{ $analytics['total_reviews'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Reviews</p>
    </div>
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-green-400">{{ $analytics['active_businesses'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Active</p>
    </div>
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-purple-400">{{ $analytics['featured_businesses'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Featured</p>
    </div>
    <div class="glass-card p-4 rounded-xl text-center">
        <p class="text-2xl font-bold text-red-400">{{ $analytics['pending_claims'] }}</p>
        <p class="text-xs text-slate-500 mt-1">Pending Claims</p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- User Growth Chart -->
    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">User Growth (Last 30 Days)</h3>
        <canvas id="userGrowthChart" height="200"></canvas>
    </div>

    <!-- Business Growth Chart -->
    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">Business Growth (Last 30 Days)</h3>
        <canvas id="businessGrowthChart" height="200"></canvas>
    </div>

    <!-- Category Distribution -->
    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">Businesses by Category</h3>
        <canvas id="categoryChart" height="250"></canvas>
    </div>

    <!-- Top Businesses -->
    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">Top Businesses by Views</h3>
        <div class="space-y-2">
            @forelse($analytics['top_businesses'] ?? [] as $business)
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-slate-500 text-xs w-5">{{ $loop->index + 1 }}.</span>
                        <span class="text-white text-sm truncate">{{ $business->name }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-24 h-1.5 rounded-full bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-600"
                                style="width: {{ $businessesMaxViews > 0 ? ($business->views_count / $businessesMaxViews) * 100 : 0 }}%">
                            </div>
                        </div>
                        <span class="text-slate-400 text-xs w-16 text-right">{{ number_format($business->views_count) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No data yet.</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Recent Reports -->
<div class="glass-card p-6 rounded-xl mb-8">
    <h3 class="text-white font-semibold mb-4">Recent Reports</h3>
    @forelse($analytics['recent_reports'] ?? [] as $report)
        <div class="flex items-center justify-between py-2 border-b border-white/5">
            <div>
                <span class="text-white text-sm">{{ $report->type }}</span>
                <span class="text-slate-500 text-xs ml-2">{{ $report->business->name ?? 'N/A' }}</span>
            </div>
            <span class="px-2 py-0.5 text-xs rounded-full
                {{ $report->status === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : '' }}
                {{ $report->status === 'resolved' ? 'bg-green-500/20 text-green-400' : '' }}
                {{ $report->status === 'rejected' ? 'bg-red-500/20 text-red-400' : '' }}">
                {{ $report->status }}
            </span>
        </div>
    @empty
        <p class="text-slate-500 text-sm">No reports yet.</p>
    @endforelse
</div>

<!-- Pending Items Alert -->
@if(($analytics['pending_imports'] ?? 0) > 0 || ($analytics['pending_claims'] ?? 0) > 0 || ($analytics['pending_reports'] ?? 0) > 0)
    <div class="glass-card p-6 rounded-xl border border-yellow-500/20">
        <h3 class="text-yellow-400 font-semibold mb-3">⚠ Pending Actions</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            @if(($analytics['pending_imports'] ?? 0) > 0)
                <a href="{{ route('admin.import.review') }}" class="p-3 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 transition">
                    <p class="text-2xl font-bold text-yellow-400">{{ $analytics['pending_imports'] }}</p>
                    <p class="text-xs text-slate-400">Pending Imports</p>
                </a>
            @endif
            @if(($analytics['pending_claims'] ?? 0) > 0)
                <a href="{{ route('admin.claims') }}" class="p-3 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 transition">
                    <p class="text-2xl font-bold text-blue-400">{{ $analytics['pending_claims'] }}</p>
                    <p class="text-xs text-slate-400">Pending Claims</p>
                </a>
            @endif
            @if(($analytics['pending_reports'] ?? 0) > 0)
                <a href="{{ route('admin.reports') }}" class="p-3 rounded-lg bg-red-500/10 hover:bg-red-500/20 transition">
                    <p class="text-2xl font-bold text-red-400">{{ $analytics['pending_reports'] }}</p>
                    <p class="text-xs text-slate-400">Pending Reports</p>
                </a>
            @endif
        </div>
    </div>
@endif

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// User Growth Chart
const userCtx = document.getElementById('userGrowthChart').getContext('2d');
new Chart(userCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_keys($analytics['user_growth']->toArray() ?? [])) !!},
        datasets: [{
            label: 'New Users',
            data: {!! json_encode(array_values($analytics['user_growth']->toArray() ?? [])) !!},
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#64748b', maxTicksLimit: 7 }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    }
});

// Business Growth Chart
const bizCtx = document.getElementById('businessGrowthChart').getContext('2d');
new Chart(bizCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_keys($analytics['business_growth']->toArray() ?? [])) !!},
        datasets: [{
            label: 'New Businesses',
            data: {!! json_encode(array_values($analytics['business_growth']->toArray() ?? [])) !!},
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#64748b', maxTicksLimit: 7 }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    }
});

// Category Distribution Chart (Doughnut)
const catCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(catCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($analytics['category_distribution']->pluck('name')->toArray() ?? []) !!},
        datasets: [{
            data: {!! json_encode($analytics['category_distribution']->pluck('businesses_count')->toArray() ?? []) !!},
            backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6', '#f97316'],
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right', labels: { color: '#94a3b8', boxWidth: 12, padding: 8 } }
        }
    }
});
</script>
@endsection