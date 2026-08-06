@extends('layouts.admin')

@php
    $tab = $tab ?? 'all';
    $tabs = [
        'all' => 'All',
        'appointments' => 'Appointments',
        'stays' => 'Stays',
        'turf' => 'Turf / Slots',
        'seats' => 'Seats / Events',
    ];
    $statusColors = [
        'pending' => 'bg-yellow-500/20 text-yellow-400',
        'confirmed' => 'bg-blue-500/20 text-blue-400',
        'completed' => 'bg-green-500/20 text-green-400',
        'cancelled' => 'bg-red-500/20 text-red-400',
        'rejected' => 'bg-red-500/20 text-red-400',
        'no_show' => 'bg-slate-500/20 text-slate-400',
        'rescheduled' => 'bg-amber-500/20 text-amber-400',
    ];
@endphp

@section('title', 'Bookings')
@section('header', 'All Bookings')

@section('content')
<div class="mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Bookings</h3>
    <p class="text-slate-500 text-sm mt-1">Every appointment, stay, turf slot and event seat across all businesses.</p>
</div>

<!-- Booking type tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.bookings', ['tab' => $key]) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $tab === $key ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $label }} ({{ $counts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-yellow-400">{{ $stats['pending'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Pending</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-blue-400">{{ $stats['confirmed'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Confirmed</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-green-400">{{ $stats['completed'] }}</div>
        <div class="text-xs text-slate-400 mt-1">Completed</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-emerald-400">₹{{ number_format($stats['paid']) }}</div>
        <div class="text-xs text-slate-400 mt-1">Cash collected</div>
    </div>
    <div class="glass-card p-4 rounded-xl">
        <div class="text-2xl font-semibold text-slate-300">₹{{ number_format($stats['unpaid']) }}</div>
        <div class="text-xs text-slate-400 mt-1">Awaiting payment</div>
    </div>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer, phone, ref..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(array_keys($statusColors) as $s)
                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="payment_status" class="input-dark w-full">
                <option value="">Any Payment</option>
                <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Unpaid</option>
                <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
            </select>
        </div>
        <div>
            <select name="business_id" class="input-dark w-full">
                <option value="">All Businesses</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" {{ request('business_id') == $business->id ? 'selected' : '' }}>{{ $business->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.bookings', ['tab' => $tab]) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Business</th>
                <th>Service</th>
                <th>Date</th>
                <th>Time</th>
                <th>Type</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $booking)
                <tr>
                    <td class="text-slate-500 text-sm">#{{ $booking->id }}</td>
                    <td>
                        <div class="text-sm font-medium text-white">{{ $booking->customer_name }}</div>
                        <div class="text-xs text-slate-500">{{ $booking->customer_phone ?? '' }}</div>
                    </td>
                    <td class="text-sm">{{ $booking->business?->name ?? 'N/A' }}</td>
                    <td class="text-sm">{{ $booking->service?->name ?? 'N/A' }}</td>
                    <td class="text-sm text-slate-400">
                        @if($booking->booking_type === 'stay')
                            {{ $booking->check_in_date?->format('M d') }} → {{ $booking->check_out_date?->format('M d') }}
                        @else
                            {{ $booking->booking_date->format('M d, Y') }}
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">
                        {{ $booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('h:i A') : '-' }}
                    </td>
                    <td>
                        <span class="text-xs text-slate-400 uppercase tracking-wide">{{ $booking->booking_type }}</span>
                    </td>
                    <td>
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $statusColors[$booking->status] ?? 'bg-slate-500/20 text-slate-400' }}">
                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </span>
                    </td>
                    <td>
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $booking->payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-yellow-500/20 text-yellow-400' }}">
                            {{ ucfirst($booking->payment_status) }}
                        </span>
                    </td>
                    <td class="text-sm">{{ number_format($booking->total_price, 2) }}</td>
                    <td>
                        <div class="flex gap-1">
                            <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                class="px-2 py-1 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20">Manage</a>
                            <form method="POST" action="{{ route('admin.bookings.destroy', $booking->id) }}"
                                data-confirm="Delete booking #{{ $booking->id }}?" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center text-slate-500 py-8">No bookings found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $bookings->withQueryString()->links() }}
</div>
@endsection
