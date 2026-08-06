@extends('layouts.admin')

@section('title', 'Booking Analytics')
@section('header', 'Booking Analytics')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Revenue & Booking Analytics</h3>
        <p class="text-slate-500 text-sm mt-1">Bookings, delivered orders and completed transport across the platform.</p>
    </div>
    <div class="flex gap-2">
        @foreach([7, 30, 90] as $r)
            <a href="{{ route('admin.booking-analytics', ['range' => $r]) }}"
                class="px-3 py-1.5 rounded-lg text-sm {{ $range === $r ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/5 text-slate-400 hover:text-white' }}">
                {{ $r }}d
            </a>
        @endforeach
        <a href="{{ route('admin.booking-analytics', ['range' => $range, 'format' => 'csv']) }}"
            class="px-3 py-1.5 rounded-lg text-sm bg-sky-500/20 text-sky-400 hover:bg-sky-500/30">Download CSV</a>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Total Revenue ({{ $range }}d)</p>
        <p class="text-2xl font-bold mt-1 text-emerald-400">{{ $currency }}{{ number_format($grandRevenue, 2) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Bookings</p>
        <p class="text-2xl font-bold mt-1 text-blue-400">{{ number_format($totals['bookings']['count']) }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ $currency }}{{ number_format($totals['bookings']['revenue'], 2) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Delivered Orders</p>
        <p class="text-2xl font-bold mt-1 text-purple-400">{{ number_format($totals['orders']['count']) }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ $currency }}{{ number_format($totals['orders']['revenue'], 2) }}</p>
    </div>
    <div class="glass-card p-5 rounded-xl">
        <p class="text-xs text-slate-500 uppercase tracking-wider">Completed Trips</p>
        <p class="text-2xl font-bold mt-1 text-cyan-400">{{ number_format($totals['trips']['count']) }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ $currency }}{{ number_format($totals['trips']['revenue'], 2) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">Revenue Trend ({{ $range }} Days)</h3>
        <canvas id="revenueChart" height="220"></canvas>
        <p class="text-slate-500 text-xs mt-2">Daily platform revenue — bookings + delivered orders + completed trips.</p>
    </div>

    <div class="glass-card p-6 rounded-xl">
        <h3 class="text-white font-semibold mb-4">Top Businesses by Revenue</h3>
        <div class="max-h-96 overflow-y-auto space-y-3">
            @forelse($perBusiness->take(15) as $biz)
                <div class="flex items-center justify-between py-2 border-b border-white/5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="text-slate-500 text-xs w-5">{{ $loop->index + 1 }}.</span>
                        <span class="text-white text-sm truncate">{{ $biz['name'] }}</span>
                        <span class="text-slate-500 text-xs">{{ $biz['bookings'] }} booking(s)</span>
                    </div>
                    <span class="text-slate-400 text-sm">{{ $currency }}{{ number_format($biz['revenue'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No bookings or revenue in this period.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="glass-card p-6 rounded-xl">
    <h3 class="text-white font-semibold mb-4">All Businesses ({{ $range }}d)</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 uppercase text-xs border-b border-white/10">
                    <th class="py-2 pr-4">#</th>
                    <th class="py-2 pr-4">Business</th>
                    <th class="py-2 pr-4 text-right">Bookings</th>
                    <th class="py-2 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($perBusiness as $biz)
                    <tr class="border-b border-white/5">
                        <td class="py-2 pr-4 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="py-2 pr-4 text-white">{{ $biz['name'] }}</td>
                        <td class="py-2 pr-4 text-right text-slate-300">{{ number_format($biz['bookings']) }}</td>
                        <td class="py-2 text-right text-emerald-400">{{ $currency }}{{ number_format($biz['revenue'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-500">No data yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: {!! json_encode(array_keys($trend->toArray())) !!},
        datasets: [{
            label: 'Revenue',
            data: {!! json_encode(array_values($trend->toArray())) !!},
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#64748b', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
    }
});
</script>
@endsection