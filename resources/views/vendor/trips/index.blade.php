@extends('vendor.layouts.dashboard')

@section('title', 'Trips')
@section('header', 'Trips')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Manage Trips</h3>
    <span class="text-slate-500 text-sm">{{ $trips->total() }} trips</span>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                <option value="started" {{ request('status') == 'started' ? 'selected' : '' }}>Started</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div>
            <input type="date" name="date" value="{{ request('date') }}" class="input-dark w-full">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('vendor.trips') }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Pickup → Drop</th>
                <th>Fare</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($trips ?? [] as $trip)
                <tr>
                    <td>
                        <p class="font-medium text-sm">{{ $trip->customer_name }}</p>
                        <p class="text-xs text-slate-500">{{ $trip->customer_phone }}</p>
                    </td>
                    <td class="text-sm">{{ $trip->vehicle->name ?? 'Any' }} ({{ $trip->seats_required }} seat{{ $trip->seats_required > 1 ? 's' : '' }})</td>
                    <td class="text-sm max-w-xs truncate">{{ $trip->pickup_location }} → {{ $trip->drop_location }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($trip->fare, 0) }}</td>
                    <td class="text-sm">{{ $trip->trip_date ? date('d M', strtotime($trip->trip_date)) : '-' }}</td>
                    <td>
                        @php $sc = match($trip->status) { 'pending' => 'yellow', 'confirmed' => 'blue', 'started' => 'purple', 'completed' => 'green', 'cancelled' => 'red', default => 'yellow' }; @endphp
                        <span class="badge badge-{{ $sc }}">{{ ucfirst($trip->status) }}</span>
                    </td>
                    <td class="text-sm">
                        @if(in_array($trip->status, ['pending', 'confirmed', 'started']))
                            <form method="POST" action="{{ route('vendor.trips.status', $trip->id) }}" class="space-y-1">
                                @csrf @method('PUT')
                                <select name="status" onchange="if(this.value && confirm('Update trip to ' + this.value + '?')) this.form.submit()" class="input-dark text-xs py-1 px-2">
                                    <option value="">Update...</option>
                                    @if($trip->status === 'pending')
                                        <option value="confirmed">Confirm</option>
                                    @endif
                                    @if(in_array($trip->status, ['confirmed']))
                                        <option value="started">Start</option>
                                    @endif
                                    @if(in_array($trip->status, ['started']))
                                        <option value="completed">Complete</option>
                                    @endif
                                    @if(in_array($trip->status, ['pending', 'confirmed']))
                                        <option value="cancelled">Cancel</option>
                                    @endif
                                </select>
                            </form>
                        @else
                            <span class="text-slate-600 text-xs">No actions</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No trips yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($trips) && $trips->hasPages())
    <div class="mt-4 text-slate-400">{{ $trips->links() }}</div>
@endif
@endsection
