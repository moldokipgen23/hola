@extends('vendor.layouts.dashboard')
@section('title', 'Vehicle Hire / Rentals')
@section('header', 'Vehicle Hire / Rentals')
@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Incoming Hire / Rental Requests</h3>
        <p class="text-slate-500 text-sm">Customers book your rental vehicles for a date range.</p>
    </div>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(['pending', 'confirmed', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('vendor.rentals', $business->id) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="space-y-3">
@forelse($rentals as $rental)
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $rental->customer_name }}</span>
                <span class="badge {{ $rental->status === 'confirmed' ? 'badge-green' : ($rental->status === 'cancelled' ? 'badge-red' : 'badge-yellow') }}">{{ ucfirst($rental->status) }}</span>
                @if($rental->with_driver)
                    <span class="badge badge-blue">With driver</span>
                @endif
            </div>
            <a href="tel:{{ $rental->customer_phone }}" class="text-sky-400 text-sm">{{ $rental->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">
                {{ $rental->vehicle->name ?? 'Vehicle' }} · {{ $rental->start_date->format('M d') }} → {{ $rental->end_date->format('M d, Y') }} ({{ $rental->days }} day(s))
            </p>
            @if($rental->notes)
                <p class="text-slate-500 text-xs mt-1">{{ $rental->notes }}</p>
            @endif
            @if($rental->cancellation_reason)
                <p class="text-red-400 text-xs mt-1">{{ $rental->cancellation_reason }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">₹{{ number_format($rental->total_price, 2) }}</p>
            <p class="text-xs text-slate-500">₹{{ number_format($rental->price_per_day, 2) }}/day</p>
            <p class="text-xs {{ $rental->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $rental->payment_status === 'paid' ? 'Paid' : 'Awaiting cash' }}</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 mt-4">
        @if($rental->status === 'pending')
            <form method="POST" action="{{ route('vendor.rentals.status', $rental->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="confirmed">
                <button class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">Confirm</button>
            </form>
            <form method="POST" action="{{ route('vendor.rentals.status', $rental->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="cancelled">
                <button class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm" onclick="return confirm('Cancel this hire?')">Cancel</button>
            </form>
        @endif
        @if($rental->status === 'confirmed')
            <form method="POST" action="{{ route('vendor.rentals.status', $rental->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="completed">
                <button class="px-3 py-2 rounded-lg bg-blue-500/10 text-blue-400 text-sm">Complete</button>
            </form>
            <form method="POST" action="{{ route('vendor.rentals.status', $rental->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="cancelled">
                <button class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button>
            </form>
        @endif
        @if($rental->payment_status !== 'paid' && $rental->status !== 'cancelled')
            <form method="POST" action="{{ route('vendor.rentals.payment-status', $rental->id) }}" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="payment_status" value="paid">
                <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button>
            </form>
        @endif
        @if($rental->customer_phone)
            <a href="tel:{{ $rental->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($rental->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
    </div>
</div>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No vehicle hire requests found.</div>
@endforelse
</div>

<div class="mt-6">{{ $rentals->withQueryString()->links() }}</div>
@endsection
