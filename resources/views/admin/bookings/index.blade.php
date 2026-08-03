@extends('layouts.admin')

@section('title', 'Bookings')
@section('header', 'All Bookings')

@section('content')
<div class="mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Bookings</h3>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="md:col-span-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                <option value="no_show" {{ request('status') == 'no_show' ? 'selected' : '' }}>No Show</option>
            </select>
        </div>
        <div>
            <input type="date" name="date" value="{{ request('date') }}" class="input-dark w-full">
        </div>
        <div>
            <input type="text" name="business_id" value="{{ request('business_id') }}" placeholder="Business ID"
                class="input-dark w-full">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.bookings') }}" class="btn-ghost">Clear</a>
            <span class="text-slate-500 text-sm self-center ml-1">{{ $bookings->total() }} bookings</span>
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
                <th>Status</th>
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
                    <td class="text-sm text-slate-400">{{ $booking->booking_date->format('M d, Y') }}</td>
                    <td class="text-sm text-slate-400">
                        {{ $booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('h:i A') : '-' }}
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'pending' => 'bg-yellow-500/20 text-yellow-400',
                                'confirmed' => 'bg-blue-500/20 text-blue-400',
                                'completed' => 'bg-green-500/20 text-green-400',
                                'cancelled' => 'bg-red-500/20 text-red-400',
                                'no_show' => 'bg-slate-500/20 text-slate-400',
                            ];
                            $color = $statusColors[$booking->status] ?? 'bg-slate-500/20 text-slate-400';
                        @endphp
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $color }}">
                            {{ ucfirst($booking->status) }}
                        </span>
                    </td>
                    <td class="text-sm">{{ number_format($booking->total_price, 2) }}</td>
                    <td>
                        <div class="flex gap-1">
                            <button onclick="toggleDetails({{ $booking->id }})"
                                class="px-2 py-1 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20">View</button>
                            <form method="POST" action="{{ route('admin.bookings.destroy', $booking->id) }}"
                                data-confirm="Delete booking #{{ $booking->id }}?" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr id="details-{{ $booking->id }}" class="hidden">
                    <td colspan="9" class="bg-white/[0.01] p-0">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 px-6 py-4 text-sm">
                            <div>
                                <span class="text-slate-500 text-xs block">Customer Email</span>
                                <span class="text-white">{{ $booking->customer_email ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-500 text-xs block">Duration</span>
                                <span class="text-white">{{ $booking->duration_minutes ?? '—' }} min</span>
                            </div>
                            <div>
                                <span class="text-slate-500 text-xs block">End Time</span>
                                <span class="text-white">
                                    {{ $booking->end_time ? \Carbon\Carbon::parse($booking->end_time)->format('h:i A') : '—' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500 text-xs block">Created</span>
                                <span class="text-white">{{ $booking->created_at->format('M d, Y h:i A') }}</span>
                            </div>
                            @if($booking->notes)
                            <div class="md:col-span-4">
                                <span class="text-slate-500 text-xs block">Notes</span>
                                <span class="text-white">{{ $booking->notes }}</span>
                            </div>
                            @endif
                            @if($booking->cancellation_reason)
                            <div class="md:col-span-4">
                                <span class="text-slate-500 text-xs block">Cancellation Reason</span>
                                <span class="text-red-400">{{ $booking->cancellation_reason }}</span>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-slate-500 py-8">No bookings found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $bookings->withQueryString()->links() }}
</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    row.classList.toggle('hidden');
}
</script>
@endsection
