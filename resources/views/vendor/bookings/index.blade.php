@extends('vendor.layouts.dashboard')

@section('title', 'Bookings')
@section('header', 'Bookings')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Bookings</h3>
    <div class="flex items-center gap-3">
        <span class="text-slate-500 text-sm">{{ $bookings->total() }} bookings</span>
        <a href="{{ route('vendor.calendar', $business->id) }}" class="btn-ghost">Calendar</a>
    </div>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="rescheduled" {{ request('status') == 'rescheduled' ? 'selected' : '' }}>Rescheduled</option>
                <option value="no_show" {{ request('status') == 'no_show' ? 'selected' : '' }}>No Show</option>
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
                <th>Payment</th>
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
                    <td class="text-sm text-slate-400">
                        @if($booking->booking_type === 'stay')
                            {{ $booking->check_in_date?->format('M d') }} → {{ $booking->check_out_date?->format('M d, Y') }}
                            <div class="text-xs text-slate-500">{{ $booking->reservation_units }} unit(s) · {{ $booking->party_size }} guest(s)</div>
                        @elseif($booking->status === 'rescheduled' && $booking->rescheduled_to_date)
                            <span class="line-through text-slate-600">{{ $booking->booking_date->format('M d, Y') }}</span>
                            <br><span class="text-amber-400">→ {{ $booking->rescheduled_to_date->format('M d, Y') }} {{ $booking->rescheduled_to_time }}</span>
                        @else
                            {{ $booking->booking_date->format('M d, Y') }}
                            @if($booking->booking_type === 'seat')<div class="text-xs text-slate-500">{{ $booking->party_size }} seat(s){{ $booking->seat_labels ? ': '.implode(', ', $booking->seat_labels) : '' }}</div>@endif
                        @endif
                    </td>
                    <td class="text-sm text-slate-400">
                        {{ $booking->booking_type === 'stay' ? 'Overnight' : ($booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('h:i A') : '-') }}
                    </td>
                    <td>
                        <span class="badge {{ $booking->payment_status === 'paid' ? 'badge-green' : 'badge-yellow' }}">
                            {{ $booking->payment_status === 'paid' ? 'Cash collected' : 'Awaiting cash' }}
                        </span>
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'pending' => 'badge-yellow',
                                'confirmed' => 'badge-blue',
                                'completed' => 'badge-green',
                                'cancelled' => 'badge-red',
                                'rejected' => 'badge-red',
                                'rescheduled' => 'bg-amber-500/20 text-amber-400',
                                'no_show' => 'bg-orange-500/20 text-orange-400',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$booking->status] ?? 'badge-yellow' }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
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
                                    <input type="hidden" name="status" value="rejected">
                                    <input type="hidden" name="cancellation_reason" value="Rejected by vendor">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20" onclick="return confirm('Reject this booking?')">Reject</button>
                                </form>
                                <button type="button" onclick="openRescheduleModal({{ $booking->id }})" class="px-2 py-1 text-xs rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20">Reschedule</button>
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
                                <form method="POST" action="{{ route('vendor.bookings.status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="no_show">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-orange-500/10 text-orange-400 hover:bg-orange-500/20">No Show</button>
                                </form>
                                <button type="button" onclick="openRescheduleModal({{ $booking->id }})" class="px-2 py-1 text-xs rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-500/20">Reschedule</button>
                            @endif
                            @if($booking->payment_status !== 'paid' && !in_array($booking->status, ['cancelled', 'rejected']))
                                <form method="POST" action="{{ route('vendor.bookings.payment-status', $booking->id) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="payment_status" value="paid">
                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20">Cash Collected</button>
                                </form>
                            @endif
                            @if($booking->customer_phone)
                                <a href="tel:{{ $booking->customer_phone }}" class="px-2 py-1 text-xs rounded-lg bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20">Call</a>
                                <a href="https://wa.me/{{ ltrim($booking->customer_phone, '0') }}" target="_blank" class="px-2 py-1 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20">WhatsApp</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @if($booking->status === 'rejected' && $booking->rejection_reason)
                <tr class="bg-red-500/5">
                    <td colspan="8" class="text-xs text-red-400 py-2">Rejected: {{ $booking->rejection_reason }}</td>
                </tr>
                @endif
                @if($booking->status === 'rescheduled' && $booking->reschedule_reason)
                <tr class="bg-amber-500/5">
                    <td colspan="8" class="text-xs text-amber-400 py-2">Rescheduled: {{ $booking->reschedule_reason }}</td>
                </tr>
                @endif
            @empty
                <tr><td colspan="8" class="text-center text-slate-500 py-8">No bookings found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $bookings->withQueryString()->links() }}
</div>

{{-- Reschedule Modal --}}
<div id="rescheduleModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="glass-card p-6 rounded-xl w-full max-w-md mx-4">
        <h3 class="text-white font-semibold text-lg mb-4">Reschedule Booking</h3>
        <form id="rescheduleForm" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">New Date *</label>
                    <input type="date" name="rescheduled_to_date" required class="input-dark w-full">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">New Time *</label>
                    <input type="time" name="rescheduled_to_time" required class="input-dark w-full">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Reason</label>
                    <textarea name="reschedule_reason" rows="2" class="input-dark w-full" placeholder="Optional reason..."></textarea>
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="submit" class="btn-primary">Confirm Reschedule</button>
                <button type="button" onclick="closeRescheduleModal()" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openRescheduleModal(bookingId) {
    const form = document.getElementById('rescheduleForm');
    form.action = `/vendor/bookings/${bookingId}/reschedule`;
    document.getElementById('rescheduleModal').classList.remove('hidden');
}
function closeRescheduleModal() {
    document.getElementById('rescheduleModal').classList.add('hidden');
}
</script>
@endpush
