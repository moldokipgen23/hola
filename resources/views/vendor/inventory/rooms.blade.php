@extends('vendor.layouts.dashboard')

@section('title', 'Room Management')
@section('header', 'Room / Stay Inventory')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="glass-card p-6 rounded-lg">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Manage room types, availability, and pricing for stay bookings.</p>
            </div>
            <a href="{{ route('vendor.businesses.modules', $business->id) }}" class="btn-ghost">Business Features</a>
        </div>
    </div>

    @forelse($services as $service)
    <form method="POST" action="{{ route('vendor.businesses.rooms.update', [$business->id, $service->id]) }}">
        @csrf @method('PUT')
        <div class="glass-card p-5 rounded-lg">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-white font-semibold">{{ $service->name }}</h3>
                    @if($service->description)
                        <p class="text-xs text-slate-500 mt-1">{{ $service->description }}</p>
                    @endif
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" {{ $service->is_active ? 'checked' : '' }}>
                    <span class="{{ $service->is_active ? 'text-green-400' : 'text-slate-500' }}">{{ $service->is_active ? 'Active' : 'Inactive' }}</span>
                </label>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Price per Night</label>
                    <input type="number" name="price" value="{{ $service->price }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Available Rooms</label>
                    <input type="number" name="inventory_units" value="{{ $service->inventory_units }}" min="0" max="10000" required class="input-dark">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Check-in Time</label>
                    <input type="text" value="{{ $service->check_in_time ?? '—' }}" class="input-dark" disabled>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Check-out Time</label>
                    <input type="text" value="{{ $service->check_out_time ?? '—' }}" class="input-dark" disabled>
                </div>
            </div>

            <div class="flex justify-between items-center mt-4">
                <div class="text-xs text-slate-500">
                    Min stay: {{ $service->min_stay_nights ?? 1 }} night(s) · Max stay: {{ $service->max_stay_nights ?? 30 }} night(s)
                </div>
                <button type="submit" class="text-sky-400 text-sm">Update</button>
            </div>
        </div>
    </form>
    @empty
    <div class="glass-card p-10 rounded-lg text-center">
        <p class="text-slate-400 mb-3">No room/stay services found.</p>
        <a href="{{ route('vendor.services.create', $business->id) }}" class="btn-primary">Create a Room Service</a>
    </div>
    @endforelse
</div>
@endsection
