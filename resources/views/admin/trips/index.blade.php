@extends('layouts.admin')

@section('title', 'Taxi Trips')
@section('header', 'Taxi Trips')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Transport Requests</h3>
        <p class="text-slate-500 text-sm mt-1">Manage every taxi / hire / rental / goods trip request.</p>
    </div>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customer, phone or pickup..."
                class="input-dark w-full">
        </div>
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                @foreach(['pending', 'confirmed', 'started', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="payment_status" class="input-dark w-full">
                <option value="">All Payment</option>
                @foreach(['pending', 'unpaid', 'paid', 'failed', 'refunded'] as $value)
                    <option value="{{ $value }}" {{ request('payment_status') == $value ? 'selected' : '' }}>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.trips') }}" class="btn-ghost">Clear</a>
            <span class="text-slate-500 text-sm self-center ml-1">{{ $trips->total() }} trips</span>
        </div>
    </div>
</form>

<div class="space-y-3">
@forelse($trips as $trip)
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $trip->customer_name }}</span>
                <span class="badge badge-blue">{{ ucfirst($trip->request_type ?? 'trip') }}</span>
                <span class="badge {{ $trip->status === 'completed' ? 'badge-green' : ($trip->status === 'cancelled' ? 'badge-red' : 'badge-yellow') }}">{{ ucfirst($trip->status) }}</span>
                <span class="text-xs text-slate-500">{{ $trip->business?->name }}</span>
            </div>
            <a href="tel:{{ $trip->customer_phone }}" class="text-sky-400 text-sm">{{ $trip->customer_phone }}</a>
            <p class="text-slate-400 text-sm mt-2">{{ $trip->pickup_location }} → {{ $trip->drop_location }}</p>
            <p class="text-slate-500 text-xs">{{ $trip->vehicle?->name ?? 'Vehicle' }} · {{ $trip->scheduled_at?->format('M d, Y h:i A') }}</p>
            @if($trip->load_description)
                <p class="text-slate-400 text-xs mt-1">Load: {{ $trip->load_description }} {{ $trip->load_weight ? '('.$trip->load_weight.' '.$trip->vehicle?->capacity_unit.')' : '' }}</p>
            @endif
            @if($trip->driver_name)
                <p class="text-slate-400 text-xs mt-1">Driver: {{ $trip->driver_name }} {{ $trip->driver_phone ? '· '.$trip->driver_phone : '' }}</p>
            @endif
            @if($trip->cancellation_reason)
                <p class="text-red-400 text-xs mt-1">{{ $trip->cancellation_reason }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">
                {{ $trip->fare_status === 'quote_required' ? 'Quote needed' : '₹'.number_format($trip->fare, 2) }}
            </p>
            <p class="text-xs {{ $trip->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">
                {{ $trip->payment_status === 'paid' ? 'Cash collected' : 'Awaiting cash' }}
            </p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mt-4">
        @if(in_array($trip->status, ['pending', 'confirmed']))
        <form method="POST" action="{{ route('admin.trips.quote', $trip->id) }}" class="flex gap-2">
            @csrf @method('PUT')
            <input type="number" name="fare" value="{{ $trip->fare ?: '' }}" step="0.01" min="0" required class="input-dark max-w-32" placeholder="Fare ₹">
            <input name="quote_notes" value="{{ $trip->quote_notes }}" class="input-dark max-w-48" placeholder="Quote note">
            <button class="text-sky-400 text-sm">Save quote</button>
        </form>
        @endif

        @php $next = ['pending' => 'confirmed', 'confirmed' => 'started', 'started' => 'completed'][$trip->status] ?? null; @endphp
        @if($next)
        <form method="POST" action="{{ route('admin.trips.status', $trip->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="{{ $next }}">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">{{ ucfirst($next) }}</button>
        </form>
        @endif

        @if(in_array($trip->status, ['pending', 'confirmed', 'started']))
        <button type="button" onclick="openCancelModal({{ $trip->id }})" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button>
        @endif

        @if($trip->payment_status !== 'paid' && $trip->status !== 'cancelled')
        <form method="POST" action="{{ route('admin.trips.payment-status', $trip->id) }}">
            @csrf @method('PUT')
            <input type="hidden" name="payment_status" value="paid">
            <button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button>
        </form>
        @endif

        @if($trip->customer_phone)
            <a href="tel:{{ $trip->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($trip->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif

        <form method="POST" action="{{ route('admin.trips.destroy', $trip->id) }}" data-confirm="Delete this trip?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
    </div>
</div>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No transport requests found.</div>
@endforelse
</div>

<div class="mt-6">{{ $trips->withQueryString()->links() }}</div>

{{-- Cancel modal --}}
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
function openCancelModal(tripId) {
    const form = document.getElementById('cancelForm');
    form.action = '/admin/trips/' + tripId + '/status';
    document.getElementById('cancelModal').classList.remove('hidden');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>
@endpush
