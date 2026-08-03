@extends('vendor.layouts.dashboard')

@section('title', 'Seat Events')
@section('header', 'Seat Event Inventory')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="glass-card p-6 rounded-lg">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Manage event seat categories, capacity, and pricing.</p>
            </div>
            <a href="{{ route('vendor.businesses.modules', $business->id) }}" class="btn-ghost">Business Features</a>
        </div>
    </div>

    @forelse($services as $service)
    <form method="POST" action="{{ route('vendor.businesses.seats.update', [$business->id, $service->id]) }}">
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

            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Total Capacity</label>
                    <input type="number" name="capacity" value="{{ $service->capacity }}" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Price per Seat</label>
                    <input type="number" name="price" value="{{ $service->price }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Unit Label</label>
                    <input type="text" value="{{ $service->unit_label ?? 'seat' }}" class="input-dark" disabled>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="text-sky-400 text-sm">Update</button>
            </div>
        </div>
    </form>
    @empty
    <div class="glass-card p-10 rounded-lg text-center">
        <p class="text-slate-400 mb-3">No seat event services found.</p>
        <a href="{{ route('vendor.services.create', $business->id) }}" class="btn-primary">Create a Seat Event Service</a>
    </div>
    @endforelse
</div>
@endsection
