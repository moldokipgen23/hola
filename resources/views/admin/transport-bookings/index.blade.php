@extends('layouts.admin')

@php
    $tab = $tab ?? 'trips';
    $tabs = [
        'trips' => 'Trips',
        'seat_bookings' => 'Seat Bookings',
        'vehicle_hire' => 'Vehicle Hire',
    ];
    $statusColors = [
        'pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'started' => 'bg-cyan-500/20 text-cyan-400',
        'completed' => 'badge-green', 'delivered' => 'badge-green', 'cancelled' => 'badge-red',
        'no_show' => 'bg-slate-500/20 text-slate-400', 'rejected' => 'badge-red', 'rescheduled' => 'bg-amber-500/20 text-amber-400',
    ];
@endphp

@section('title', 'Transport Bookings')
@section('header', 'Transport Bookings')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div>
        <h3 class="text-white font-semibold text-lg">Transport Bookings</h3>
        <p class="text-slate-500 text-sm mt-1">Every taxi trip, bus seat booking and vehicle hire in one place.</p>
    </div>
</div>

<!-- Transport record tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.transport-bookings', ['tab' => $key]) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ $tab === $key ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $label }} ({{ $counts[$key] ?? 0 }})
        </a>
    @endforeach
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(['pending', 'confirmed', 'started', 'completed', 'cancelled', 'no_show', 'rejected', 'rescheduled'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.transport-bookings', ['tab' => $tab]) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="space-y-3">
@forelse($records as $record)
@if($tab === 'trips')
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $record->customer_name }}</span>
                <span class="badge badge-blue">{{ ucfirst($record->request_type ?? 'trip') }}</span>
                <span class="badge {{ $statusColors[$record->status] ?? 'badge-yellow' }}">{{ ucfirst($record->status) }}</span>
                <span class="text-xs text-slate-500">{{ $record->business?->name }}</span>
            </div>
            <a href="tel:{{ $record->customer_phone }}" class="text-sky-400 text-sm">{{ $record->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">{{ $record->pickup_location }} → {{ $record->drop_location }}</p>
            <p class="text-slate-500 text-xs">{{ $record->vehicle?->name ?? 'Vehicle' }} · {{ $record->scheduled_at?->format('M d, Y h:i A') }}</p>
            @if($record->load_description)
                <p class="text-slate-400 text-xs mt-1">Load: {{ $record->load_description }} {{ $record->load_weight ? '('.$record->load_weight.' '.$record->vehicle?->capacity_unit.')' : '' }}</p>
            @endif
            @if($record->driver_name)
                <p class="text-slate-400 text-xs mt-1">Driver: {{ $record->driver_name }} {{ $record->driver_phone ? '· '.$record->driver_phone : '' }}</p>
            @endif
            @if($record->cancellation_reason)
                <p class="text-red-400 text-xs mt-1">{{ $record->cancellation_reason }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">
                {{ $record->fare_status === 'quote_required' ? 'Quote needed' : '₹'.number_format($record->fare, 2) }}
            </p>
            <p class="text-xs {{ $record->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">
                {{ $record->payment_status === 'paid' ? 'Cash collected' : 'Awaiting cash' }}
            </p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 mt-4">
        @if(in_array($record->status, ['pending', 'confirmed']))
        <form method="POST" action="{{ route('admin.trips.quote', $record->id) }}" class="flex gap-2">
            @csrf @method('PUT')
            <input type="number" name="fare" value="{{ $record->fare ?: '' }}" step="0.01" min="0" required class="input-dark max-w-32" placeholder="Fare ₹">
            <input name="quote_notes" value="{{ $record->quote_notes }}" class="input-dark max-w-48" placeholder="Quote note">
            <button class="text-sky-400 text-sm">Save quote</button>
        </form>
        @endif
        @php $next = ['pending' => 'confirmed', 'confirmed' => 'started', 'started' => 'completed'][$record->status] ?? null; @endphp
        @if($next)
        <form method="POST" action="{{ route('admin.trips.status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="{{ $next }}">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">{{ ucfirst($next) }}</button>
        </form>
        @endif
        @if(in_array($record->status, ['pending', 'confirmed', 'started']))
        <button type="button" onclick="openCancelModal('{{ route('admin.trips.status', $record->id) }}')" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button>
        @endif
        @if($record->payment_status !== 'paid' && $record->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.trips.payment-status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="payment_status" value="paid">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button>
        </form>
        @endif
        @if($record->customer_phone)
            <a href="tel:{{ $record->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($record->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
        <form method="POST" action="{{ route('admin.trips.destroy', $record->id) }}" data-confirm="Delete this trip?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
    </div>
</div>
@elseif($tab === 'seat_bookings')
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $record->customer_name }}</span>
                <span class="badge {{ $statusColors[$record->status] ?? 'badge-yellow' }}">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</span>
                <span class="text-xs text-slate-500">{{ $record->business?->name }}</span>
            </div>
            <a href="tel:{{ $record->customer_phone }}" class="text-sky-400 text-sm">{{ $record->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">
                {{ $record->schedule->origin ?? '' }} → {{ $record->schedule->destination ?? '' }}
                · {{ $record->schedule->departure_date?->format('M d, Y') }} {{ $record->schedule->departure_time }}
                · {{ $record->schedule->vehicle->name ?? 'Vehicle' }}
            </p>
            <p class="text-slate-400 text-sm mt-1">Seats: <span class="text-white font-medium">{{ $record->seat_labels ? implode(', ', $record->seat_labels) : $record->seats.' seat(s)' }}</span></p>
            @if($record->cancellation_reason)
                <p class="text-red-400 text-xs mt-1">{{ $record->cancellation_reason }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">₹{{ number_format($record->total_price, 2) }}</p>
            <p class="text-xs {{ $record->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $record->payment_status === 'paid' ? 'Paid' : 'Awaiting cash' }}</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 mt-4">
        @php $nextSb = ['pending' => 'confirmed', 'confirmed' => 'completed'][$record->status] ?? null; @endphp
        @if($nextSb)
        <form method="POST" action="{{ route('admin.seat-bookings.status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="{{ $nextSb }}">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">{{ ucfirst($nextSb) }}</button>
        </form>
        @endif
        @if(in_array($record->status, ['pending', 'confirmed']))
        <button type="button" onclick="openCancelModal('{{ route('admin.seat-bookings.status', $record->id) }}')" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button>
        @endif
        @if($record->payment_status !== 'paid' && $record->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.seat-bookings.payment-status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="payment_status" value="paid">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button>
        </form>
        @endif
        @if($record->customer_phone)
            <a href="tel:{{ $record->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($record->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
        <form method="POST" action="{{ route('admin.seat-bookings.destroy', $record->id) }}" data-confirm="Delete this seat booking?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
    </div>
</div>
@else
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $record->customer_name }}</span>
                <span class="badge {{ $statusColors[$record->status] ?? 'badge-yellow' }}">{{ ucfirst($record->status) }}</span>
                @if($record->with_driver)<span class="badge badge-blue">With driver</span>@endif
                <span class="text-xs text-slate-500">{{ $record->business?->name }}</span>
            </div>
            <a href="tel:{{ $record->customer_phone }}" class="text-sky-400 text-sm">{{ $record->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">
                {{ $record->vehicle->name ?? 'Vehicle' }} · {{ $record->start_date->format('M d') }} → {{ $record->end_date->format('M d, Y') }} ({{ $record->days }} day(s))
            </p>
            @if($record->terms_accepted)<p class="text-emerald-400 text-xs mt-1">✓ Terms accepted</p>@endif
            @if($record->notes)<p class="text-slate-500 text-xs mt-1">{{ $record->notes }}</p>@endif
            @if($record->cancellation_reason)<p class="text-red-400 text-xs mt-1">{{ $record->cancellation_reason }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">₹{{ number_format($record->total_price, 2) }}</p>
            <p class="text-xs text-slate-500">₹{{ number_format($record->price_per_day, 2) }}/day</p>
            <p class="text-xs {{ $record->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $record->payment_status === 'paid' ? 'Paid' : 'Awaiting cash' }}</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 mt-4">
        @php $nextR = ['pending' => 'confirmed', 'confirmed' => 'completed'][$record->status] ?? null; @endphp
        @if($nextR)
        <form method="POST" action="{{ route('admin.vehicle-rentals.status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="{{ $nextR }}">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">{{ ucfirst($nextR) }}</button>
        </form>
        @endif
        @if(in_array($record->status, ['pending', 'confirmed']))
        <button type="button" onclick="openCancelModal('{{ route('admin.vehicle-rentals.status', $record->id) }}')" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button>
        @endif
        @if($record->payment_status !== 'paid' && $record->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.vehicle-rentals.payment-status', $record->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="payment_status" value="paid">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button>
        </form>
        @endif
        @if($record->customer_phone)
            <a href="tel:{{ $record->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($record->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
        <form method="POST" action="{{ route('admin.vehicle-rentals.destroy', $record->id) }}" data-confirm="Delete this hire?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
    </div>
</div>
@endif
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No records found.</div>
@endforelse
</div>

<div class="mt-6">{{ $records->links() }}</div>

{{-- Cancel modal (trips) --}}
<div id="cancelModal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center">
    <div class="glass-card p-6 rounded-xl w-full max-w-md mx-4">
        <h3 class="text-white font-semibold text-lg mb-4">Cancel Transport Request</h3>
        <form id="cancelForm" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="cancelled">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Reason (optional)</label>
                    <textarea name="cancellation_reason" rows="2" class="input-dark w-full"></textarea>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Driver (optional)</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input name="driver_name" class="input-dark" placeholder="Driver name">
                        <input name="driver_phone" class="input-dark" placeholder="Driver phone">
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="submit" class="btn-danger">Cancel Request</button>
                <button type="button" onclick="closeCancelModal()" class="btn-ghost">Close</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openCancelModal(actionUrl) {
    const form = document.getElementById('cancelForm');
    form.action = actionUrl;
    document.getElementById('cancelModal').classList.remove('hidden');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>
@endpush
