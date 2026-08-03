@extends('vendor.layouts.dashboard')
@section('title', 'Transport Requests')
@section('header', 'Transport Requests')
@section('content')
<div class="flex justify-between items-center mb-6"><div><h3 class="text-white font-semibold text-lg">Transport Requests</h3><p class="text-slate-500 text-sm">Confirm directly with customers. Payments are cash/offline.</p></div><a href="{{ route('vendor.vehicles', $business->id) }}" class="btn-primary">Manage Fleet</a></div>
<form method="GET" class="glass-card p-4 rounded-xl mb-4"><div class="grid grid-cols-1 md:grid-cols-3 gap-3"><select name="status" class="input-dark"><option value="">All statuses</option>@foreach(['pending','confirmed','started','completed','cancelled'] as $status)<option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>@endforeach</select><input name="search" value="{{ request('search') }}" class="input-dark" placeholder="Customer, phone or pickup"><div><button class="btn-primary">Filter</button> <a href="{{ route('vendor.trips', $business->id) }}" class="btn-ghost">Clear</a></div></div></form>

<div class="space-y-3">
@forelse($trips as $trip)
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div><div class="flex gap-2 items-center"><span class="text-white font-semibold">{{ $trip->customer_name }}</span><span class="badge badge-blue">{{ ucfirst($trip->request_type) }}</span><span class="badge {{ $trip->status === 'completed' ? 'badge-green' : ($trip->status === 'cancelled' ? 'badge-red' : 'badge-yellow') }}">{{ ucfirst($trip->status) }}</span></div><a href="tel:{{ $trip->customer_phone }}" class="text-sky-400 text-sm">{{ $trip->customer_phone }}</a><p class="text-slate-400 text-sm mt-2">{{ $trip->pickup_location }} → {{ $trip->drop_location }}</p><p class="text-slate-500 text-xs">{{ $trip->vehicle->name ?? 'Vehicle' }} · {{ $trip->scheduled_at?->format('M d, Y h:i A') }}</p>@if($trip->load_description)<p class="text-slate-400 text-xs mt-1">Load: {{ $trip->load_description }} {{ $trip->load_weight ? '('.$trip->load_weight.' '.$trip->vehicle?->capacity_unit.')' : '' }}</p>@endif</div>
        <div class="text-right"><p class="text-white font-bold text-lg">{{ $trip->fare_status === 'quote_required' ? 'Quote needed' : '₹'.number_format($trip->fare, 2) }}</p><p class="text-xs {{ $trip->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">{{ $trip->payment_status === 'paid' ? 'Cash collected' : 'Awaiting cash' }}</p></div>
    </div>
    <div class="flex flex-wrap gap-2 mt-4">
        @if(in_array($trip->status, ['pending','confirmed']))<form method="POST" action="{{ route('vendor.trips.quote', $trip->id) }}" class="flex gap-2">@csrf @method('PUT')<input type="number" name="fare" value="{{ $trip->fare ?: '' }}" step="0.01" min="0" required class="input-dark max-w-32" placeholder="Fare ₹"><input name="quote_notes" value="{{ $trip->quote_notes }}" class="input-dark max-w-48" placeholder="Quote note"><button class="text-sky-400 text-sm">Save quote</button></form>@endif
        @php $next = ['pending' => 'confirmed', 'confirmed' => 'started', 'started' => 'completed'][$trip->status] ?? null; @endphp
        @if($next)<form method="POST" action="{{ route('vendor.trips.status', $trip->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $next }}"><button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">{{ ucfirst($next) }}</button></form>@endif
        @if(in_array($trip->status, ['pending','confirmed','started']))<form method="POST" action="{{ route('vendor.trips.status', $trip->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><button class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Cancel</button></form>@endif
        @if($trip->payment_status !== 'paid' && $trip->status !== 'cancelled')<form method="POST" action="{{ route('vendor.trips.payment-status', $trip->id) }}">@csrf @method('PUT')<input type="hidden" name="payment_status" value="paid"><button class="px-3 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm">Cash Collected</button></form>@endif
        @if($trip->customer_phone)
            <a href="tel:{{ $trip->customer_phone }}" class="px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 text-sm">Call</a>
            <a href="https://wa.me/{{ ltrim($trip->customer_phone, '0') }}" target="_blank" class="px-3 py-2 rounded-lg bg-green-500/10 text-green-400 text-sm">WhatsApp</a>
        @endif
    </div>
</div>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No transport requests found.</div>
@endforelse
</div>
<div class="mt-6">{{ $trips->links() }}</div>
@endsection
