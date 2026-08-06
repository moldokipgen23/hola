@extends('layouts.admin')

@section('title', 'Seat Bookings')
@section('header', 'Seat Bookings')

@section('content')
<div class="mb-6">
    <h3 class="text-white font-semibold text-lg">Transport Seat Bookings</h3>
    <p class="text-slate-500 text-sm mt-1">All seat reservations across transport vendors.</p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer or phone..." class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(['pending', 'confirmed', 'completed', 'cancelled', 'no_show'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div><input type="date" name="date" value="{{ request('date') }}" class="input-dark w-full"></div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.seat-bookings') }}" class="btn-ghost">Clear</a>
            <span class="text-slate-500 text-sm self-center ml-1">{{ $bookings->total() }} bookings</span>
        </div>
    </div>
</form>

<div class="space-y-3">
@forelse($bookings as $booking)
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $booking->customer_name }}</span>
                <span class="badge {{ $booking->status === 'confirmed' ? 'badge-green' : ($booking->status === 'cancelled' ? 'badge-red' : 'badge-yellow') }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                <span class="text-xs text-slate-500">{{ $booking->business?->name }}</span>
            </div>
            <a href="tel:{{ $booking->customer_phone }}" class="text-sky-400 text-sm">{{ $booking->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">
                {{ $booking->schedule->origin ?? '' }} → {{ $booking->schedule->destination ?? '' }}
                · {{ $booking->schedule->departure_date?->format('M d, Y') }} {{ $booking->schedule->departure_time }}
                · {{ $booking->schedule->vehicle->name ?? 'Vehicle' }}
            </p>
            <p class="text-slate-400 text-sm mt-1">Seats: <span class="text-white font-medium">{{ $booking->seat_labels ? implode(', ', $booking->seat_labels) : $booking->seats.' seat(s)' }}</span></p>
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">₹{{ number_format($booking->total_price, 2) }}</p>
            <p class="text-xs {{ $booking->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $booking->payment_status === 'paid' ? 'Paid' : 'Awaiting cash' }}</p>
        </div>
    </div>
    <div class="flex gap-2 mt-4">
        <form method="POST" action="{{ route('admin.seat-bookings.destroy', $booking->id) }}" data-confirm="Delete this seat booking?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
        @if($booking->customer_phone)
            <a href="tel:{{ $booking->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($booking->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
    </div>
</div>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No seat bookings found.</div>
@endforelse
</div>

<div class="mt-6">{{ $bookings->withQueryString()->links() }}</div>
@endsection
