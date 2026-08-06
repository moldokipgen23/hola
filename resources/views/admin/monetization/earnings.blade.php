@extends('layouts.admin')

@section('title', 'Platform Earnings')
@section('header', 'Platform Earnings')

@section('content')
<div class="mb-6">
    <h3 class="text-white font-semibold text-lg">Commission earnings</h3>
    <p class="text-slate-500 text-sm mt-1">Platform commission recorded automatically when orders are delivered, bookings complete, and trips complete.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <input type="date" name="from" value="{{ request('from') }}" class="input-dark">
        <input type="date" name="to" value="{{ request('to') }}" class="input-dark">
        <button class="btn-primary px-6">Filter</button>
        <a href="{{ route('admin.earnings') }}" class="btn-ghost">Clear</a>
    </div>
</form>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-emerald-400">₹{{ number_format($earnings['total'], 2) }}</div>
        <div class="text-xs text-slate-400 mt-1">Total commission</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-white">{{ $earnings['count'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Commission transactions</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-blue-400">₹{{ number_format(($earnings['by_type']['order_commission']['amount'] ?? 0), 2) }}</div>
        <div class="text-xs text-slate-400 mt-1">From orders</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-purple-400">₹{{ number_format(($earnings['by_type']['booking_commission']['amount'] ?? 0) + ($earnings['by_type']['trip_commission']['amount'] ?? 0), 2) }}</div>
        <div class="text-xs text-slate-400 mt-1">From bookings + trips</div>
    </div>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <div class="px-5 py-4 border-b border-white/10"><h4 class="text-white font-semibold">By business</h4></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Business</th>
                <th>Transactions</th>
                <th>Commission</th>
            </tr>
        </thead>
        <tbody>
            @forelse($earnings['by_business'] as $row)
                <tr>
                    <td class="text-sm">{{ $row['business_name'] }}</td>
                    <td class="text-sm">{{ $row['count'] }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-slate-500 py-8">No commission recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
