@extends('layouts.admin')

@php
    $statusColors = [
        'pending' => 'bg-yellow-500/20 text-yellow-400',
        'confirmed' => 'bg-blue-500/20 text-blue-400',
        'completed' => 'bg-green-500/20 text-green-400',
        'cancelled' => 'bg-red-500/20 text-red-400',
        'rejected' => 'bg-red-500/20 text-red-400',
        'no_show' => 'bg-slate-500/20 text-slate-400',
        'rescheduled' => 'bg-amber-500/20 text-amber-400',
    ];
    $transitions = [
        'pending' => ['confirmed', 'rejected', 'cancelled', 'rescheduled'],
        'confirmed' => ['completed', 'cancelled', 'no_show', 'rescheduled'],
    ];
    $allowed = $transitions[$booking->status] ?? [];
@endphp

@section('title', 'Booking #'.$booking->id)
@section('header', 'Booking #'.$booking->id)

@section('content')
<div class="mb-6 flex flex-col md:flex-row md:justify-between gap-3">
    <div>
        <h3 class="text-white font-semibold text-lg">{{ $booking->service?->name ?? 'Service' }}</h3>
        <p class="text-slate-500 text-sm mt-1">{{ $booking->business?->name }} · {{ $booking->business?->phone ?? '' }}</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="px-3 py-1 text-xs rounded-full {{ $statusColors[$booking->status] ?? 'bg-slate-500/20 text-slate-400' }}">
            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
        </span>
        <span class="px-3 py-1 text-xs rounded-full {{ $booking->payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-yellow-500/20 text-yellow-400' }}">
            {{ ucfirst($booking->payment_status) }} · {{ ucfirst($booking->payment_method) }}
        </span>
        <a href="{{ route('admin.bookings') }}" class="btn-ghost text-xs px-3 py-2">← All bookings</a>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 rounded-lg mb-6">{{ $errors->first() }}</div>
@endif

<!-- Status + payment actions -->
@if(count($allowed) || $booking->payment_status !== 'paid')
<div class="glass-card p-5 rounded-xl mb-6">
    <h4 class="text-white font-semibold mb-4">Admin Actions</h4>
    <div class="flex flex-wrap gap-2">
        @foreach($allowed as $action)
            <form method="POST" action="{{ route('admin.bookings.status', $booking->id) }}"
                data-confirm="Mark booking #{{ $booking->id }} as {{ $action }}?" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $action }}">
                <button type="submit"
                    class="px-3 py-1.5 text-xs rounded-lg font-medium
                        {{ in_array($action, ['cancelled', 'rejected'], true) ? 'bg-red-500/10 text-red-400 hover:bg-red-500/20' : 'bg-blue-500/10 text-blue-400 hover:bg-blue-500/20' }}">
                    {{ ucfirst(str_replace('_', ' ', $action)) }}
                </button>
            </form>
        @endforeach
        @if($booking->payment_status !== 'paid')
            <form method="POST" action="{{ route('admin.bookings.payment-status', $booking->id) }}"
                data-confirm="Mark cash as collected for #{{ $booking->id }}?" class="inline">
                @csrf @method('PUT')
                <input type="hidden" name="payment_status" value="paid">
                <button type="submit" class="px-3 py-1.5 text-xs rounded-lg font-medium bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20">
                    Mark cash collected
                </button>
            </form>
        @endif
    </div>
    @if(in_array('cancelled', $allowed, true))
    <form method="POST" action="{{ route('admin.bookings.status', $booking->id) }}" class="mt-4 inline-flex gap-2 items-end">
        @csrf @method('PUT')
        <input type="hidden" name="status" value="cancelled">
        <input type="text" name="reason" placeholder="Cancellation reason" class="input-dark text-sm max-w-xs">
        <button type="submit" class="btn-ghost text-xs px-3 py-2">Cancel with reason</button>
    </form>
    @endif
</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Customer -->
    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-4">Customer</h4>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Name</dt>
                <dd class="text-white text-right">{{ $booking->customer_name }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Phone</dt>
                <dd class="text-white text-right">{{ $booking->customer_phone }}</dd>
            </div>
            @if($booking->customer_email)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Email</dt>
                <dd class="text-white text-right break-all">{{ $booking->customer_email }}</dd>
            </div>
            @endif
            @if($booking->client_reference)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Reference</dt>
                <dd class="text-white text-right font-mono">{{ $booking->client_reference }}</dd>
            </div>
            @endif
            @if($booking->user_id)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Account</dt>
                <dd class="text-white text-right">User #{{ $booking->user_id }}</dd>
            </div>
            @endif
            @if($booking->notes)
            <div>
                <dt class="text-slate-500 mb-1">Notes</dt>
                <dd class="text-white">{{ $booking->notes }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <!-- Schedule -->
    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-4">Schedule</h4>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Date</dt>
                <dd class="text-white text-right">{{ $booking->booking_date?->format('M d, Y') }}</dd>
            </div>
            @if($booking->check_in_date)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Check-in</dt>
                <dd class="text-white text-right">{{ $booking->check_in_date->format('M d, Y') }}</dd>
            </div>
            @endif
            @if($booking->check_out_date)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Check-out</dt>
                <dd class="text-white text-right">{{ $booking->check_out_date->format('M d, Y') }}</dd>
            </div>
            @endif
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Time</dt>
                <dd class="text-white text-right">
                    {{ $booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('h:i A') : '-' }}
                    @if($booking->end_time)
                        → {{ \Carbon\Carbon::parse($booking->end_time)->format('h:i A') }}
                    @endif
                </dd>
            </div>
            @if($booking->duration_minutes)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Duration</dt>
                <dd class="text-white text-right">{{ $booking->duration_minutes }} min</dd>
            </div>
            @endif
            @if($booking->party_size)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Party size</dt>
                <dd class="text-white text-right">{{ $booking->party_size }}</dd>
            </div>
            @endif
            @if($booking->reservation_units)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Units</dt>
                <dd class="text-white text-right">{{ $booking->reservation_units }}</dd>
            </div>
            @endif
            @if($booking->seat_labels)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Seats</dt>
                <dd class="text-white text-right">{{ implode(', ', $booking->seat_labels) }}</dd>
            </div>
            @endif
            @if($booking->timeSlot)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Slot</dt>
                <dd class="text-white text-right">{{ $booking->timeSlot->start_time }}–{{ $booking->timeSlot->end_time }}</dd>
            </div>
            @endif
            @if($booking->rescheduled_to_date || $booking->rescheduled_to_time)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Rescheduled to</dt>
                <dd class="text-amber-400 text-right">{{ $booking->rescheduled_to_date?->format('M d, Y') }} {{ $booking->rescheduled_to_time }}</dd>
            </div>
            @endif
        </dl>
    </div>
</div>

<!-- Money + history -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-4">Payment</h4>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Unit price</dt>
                <dd class="text-white text-right">₹{{ number_format($booking->unit_price, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Total</dt>
                <dd class="text-white text-right font-semibold">₹{{ number_format($booking->total_price, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Method</dt>
                <dd class="text-white text-right capitalize">{{ $booking->payment_method }}</dd>
            </div>
            @if(($booking->metadata['payment_mode'] ?? null) === 'offline')
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Mode</dt>
                <dd class="text-white text-right">Offline (pay business directly)</dd>
            </div>
            @endif
        </dl>
    </div>

    <div class="glass-card p-5 rounded-xl">
        <h4 class="text-white font-semibold mb-4">History</h4>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Created</dt>
                <dd class="text-white text-right">{{ $booking->created_at->format('M d, Y h:i A') }}</dd>
            </div>
            @if($booking->confirmed_at)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Confirmed</dt>
                <dd class="text-white text-right">{{ $booking->confirmed_at->format('M d, Y h:i A') }}</dd>
            </div>
            @endif
            @if($booking->completed_at)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Completed</dt>
                <dd class="text-white text-right">{{ $booking->completed_at->format('M d, Y h:i A') }}</dd>
            </div>
            @endif
            @if($booking->cancelled_at)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Cancelled</dt>
                <dd class="text-red-400 text-right">{{ $booking->cancelled_at->format('M d, Y h:i A') }}</dd>
            </div>
            @endif
            @if($booking->cancellation_reason)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Cancel reason</dt>
                <dd class="text-red-400 text-right">{{ $booking->cancellation_reason }}</dd>
            </div>
            @endif
            @if($booking->rejection_reason)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Reject reason</dt>
                <dd class="text-red-400 text-right">{{ $booking->rejection_reason }}</dd>
            </div>
            @endif
            @if($booking->rescheduled_at)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Rescheduled</dt>
                <dd class="text-amber-400 text-right">{{ $booking->rescheduled_at->format('M d, Y h:i A') }}</dd>
            </div>
            @endif
        </dl>
    </div>
</div>
@endsection
