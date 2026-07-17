@extends('vendor.layouts.dashboard')

@section('title', 'Bookings')
@section('header', 'Bookings')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Bookings</h3>
    <span class="text-slate-500 text-sm">{{ $bookings->total() }} bookings</span>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div>
            <input type="date" name="date" value="{{ request('date') }}" class="input-dark w-full">
        </div>
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer..." class="input-dark w-full">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('vendor.bookings', $business->id) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
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
                    <td class="text-sm">{{ $booking->service->name ?? '-' }}</td>
                    <td class="text-sm text-slate-400">{{ $booking->booking_date->format('M d, Y') }}</td>
                    <td class="text-sm text-slate-400">
                        {{ $booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('h:i A') : '-' }}
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'pending' => 'badge-yellow',
                                'confirmed' => 'badge-blue',
                                'completed' => 'badge-green',
                                'cancelled' => 'badge-red',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$booking->status] ?? 'badge-yellow' }}">{{ ucfirst($booking->status) }}</span>
                    </td>
                    <td>
                        <div class="flex gap-1 flex-wrap">
                            @if($booking->status === 'pending')
                                <form method="POST" action="{{ route('vendor.bookings.status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20">Confirm</button>
                                </form>
                                <form method="POST" action="{{ route('vendor.bookings.status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20">Cancel</button>
                                </form>
                            @endif
                            @if($booking->status === 'confirmed')
                                <form method="POST" action="{{ route('vendor.bookings.status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20">Complete</button>
                                </form>
                                <form method="POST" action="{{ route('vendor.bookings.status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-500 py-8">No bookings found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $bookings->withQueryString()->links() }}
</div>
@endsection
