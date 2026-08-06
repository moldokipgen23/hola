@extends('vendor.layouts.dashboard')

@section('title', 'Stay Board')
@section('header', 'Stay Board')

@section('content')
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Stay Board</h3>
        <p class="text-slate-500 text-sm">Today's arrivals, in-house guests and departures.</p>
    </div>
    <a href="{{ route('vendor.bookings', $business->id) }}" class="btn-ghost">All bookings</a>
</div>

@if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 rounded-lg mb-6">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Arrivals -->
    <div class="glass-card p-5 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-white font-semibold">Arrivals today</h4>
            <span class="badge badge-blue">{{ $arrivals->count() }}</span>
        </div>
        <div class="space-y-3">
            @forelse($arrivals as $booking)
                <div class="bg-white/5 rounded-lg p-3">
                    <div class="text-sm font-medium text-white">{{ $booking->customer_name }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">{{ $booking->service?->name }} · {{ $booking->reservation_units }} unit(s)</div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ $booking->customer_phone }}</div>
                    <form method="POST" action="{{ route('vendor.bookings.check-in', $booking->id) }}" class="mt-2 flex gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="room_number" placeholder="Room #" class="input-dark text-xs w-24">
                        <button class="btn-primary text-xs px-3 py-1.5">Check in</button>
                    </form>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No arrivals today.</p>
            @endforelse
        </div>
    </div>

    <!-- In house -->
    <div class="glass-card p-5 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-white font-semibold">In house</h4>
            <span class="badge badge-green">{{ $inHouse->count() }}</span>
        </div>
        <div class="space-y-3">
            @forelse($inHouse as $booking)
                <div class="bg-white/5 rounded-lg p-3">
                    <div class="text-sm font-medium text-white">{{ $booking->customer_name }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">
                        {{ $booking->room_number ? 'Room '.$booking->room_number.' · ' : '' }}{{ $booking->service?->name }}
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        Check-out {{ $booking->check_out_date->format('M d') }}
                        @if($booking->payment_status !== 'paid')
                            · <span class="text-yellow-400">unpaid ₹{{ number_format($booking->total_price, 0) }}</span>
                        @endif
                    </div>
                    <div class="flex gap-2 mt-2">
                        <form method="POST" action="{{ route('vendor.bookings.check-out', $booking->id) }}" data-confirm="Check out {{ $booking->customer_name }}?">
                            @csrf @method('PUT')
                            <button class="btn-ghost text-xs px-3 py-1.5">Check out</button>
                        </form>
                        @if($booking->payment_status !== 'paid')
                            <form method="POST" action="{{ route('vendor.bookings.payment-status', $booking->id) }}" data-confirm="Mark cash as collected?">
                                @csrf @method('PUT')
                                <input type="hidden" name="payment_status" value="paid">
                                <button class="text-emerald-400 text-xs px-2 py-1.5">Collect cash</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No guests in house.</p>
            @endforelse
        </div>
    </div>

    <!-- Departures -->
    <div class="glass-card p-5 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-white font-semibold">Departures today</h4>
            <span class="badge badge-yellow">{{ $departures->count() }}</span>
        </div>
        <div class="space-y-3">
            @forelse($departures as $booking)
                <div class="bg-white/5 rounded-lg p-3">
                    <div class="text-sm font-medium text-white">{{ $booking->customer_name }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">{{ $booking->room_number ? 'Room '.$booking->room_number.' · ' : '' }}{{ $booking->service?->name }}</div>
                    <form method="POST" action="{{ route('vendor.bookings.check-out', $booking->id) }}" class="mt-2" data-confirm="Check out {{ $booking->customer_name }}?">
                        @csrf @method('PUT')
                        <button class="btn-primary text-xs px-3 py-1.5">Check out</button>
                    </form>
                </div>
            @empty
                <p class="text-slate-500 text-sm">No departures today.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
