@extends('vendor.layouts.dashboard')
@section('title', 'Fleet & Transport Options')
@section('header', 'Fleet & Transport Options')
@section('content')
<div class="flex justify-between items-center mb-6"><div><h3 class="text-white font-semibold text-lg">{{ $business->name }}</h3><p class="text-slate-500 text-sm">Add taxis, shared vehicles, rentals, trucks, and goods carriers.</p></div><a href="{{ route('vendor.trips', $business->id) }}" class="btn-primary">View Requests</a></div>

<form method="POST" action="{{ route('vendor.vehicles.store', $business->id) }}" class="glass-card p-5 rounded-xl mb-6">
    @csrf
    <h4 class="text-white font-medium mb-4">Add transport option</h4>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <input name="name" required class="input-dark" placeholder="Vehicle name">
        <select name="type" class="input-dark">@foreach(['car','bolero','suv','van','auto','bike','bus','truck','pickup','tempo'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select>
        <select name="service_mode" class="input-dark"><option value="taxi">Taxi / ride</option><option value="shared">Shared seats</option><option value="rental">Rental</option><option value="goods">Truck / goods</option></select>
        <input type="number" name="seats" value="4" min="1" required class="input-dark" placeholder="Seats">
        <div class="flex"><input type="number" name="capacity_value" step="0.01" min="0" class="input-dark" placeholder="Load capacity"><select name="capacity_unit" class="input-dark"><option value="seats">seats</option><option value="kg">kg</option><option value="tons">tons</option><option value="vehicle">vehicle</option></select></div>
        <input type="number" name="base_fare" value="0" step="0.01" min="0" required class="input-dark" placeholder="Base fare ₹">
        <input type="number" name="fare_per_km" value="0" step="0.01" min="0" required class="input-dark" placeholder="₹ per km">
        <input type="number" name="min_km" value="1" min="1" class="input-dark" placeholder="Minimum km">
        <input name="registration_number" class="input-dark" placeholder="Registration (private)">
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="requires_quote" value="1"> Always request quote</label>
    </div>
    <button class="btn-primary mt-4">Add Transport Option</button>
</form>

<div class="space-y-3">
@forelse($vehicles as $vehicle)
<form method="POST" action="{{ route('vendor.vehicles.update', [$business->id, $vehicle->id]) }}" class="glass-card p-4 rounded-xl">@csrf @method('PUT')
    <div class="flex justify-between mb-3"><div><span class="text-white font-medium">{{ $vehicle->name }}</span> <span class="badge badge-blue ml-2">{{ ucfirst($vehicle->service_mode) }}</span></div><span class="text-xs text-slate-500">{{ $vehicle->trips_count }} active requests</span></div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <input name="name" value="{{ $vehicle->name }}" required class="input-dark">
        <select name="service_mode" class="input-dark">@foreach(['taxi','shared','rental','goods'] as $mode)<option value="{{ $mode }}" {{ $vehicle->service_mode === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>@endforeach</select>
        <input type="number" name="seats" value="{{ $vehicle->seats }}" min="1" required class="input-dark">
        <div class="flex"><input type="number" name="capacity_value" value="{{ $vehicle->capacity_value }}" step="0.01" class="input-dark"><select name="capacity_unit" class="input-dark">@foreach(['seats','kg','tons','vehicle'] as $unit)<option {{ $vehicle->capacity_unit === $unit ? 'selected' : '' }}>{{ $unit }}</option>@endforeach</select></div>
        <select name="availability_status" class="input-dark">@foreach(['available','busy','offline'] as $status)<option value="{{ $status }}" {{ $vehicle->availability_status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>@endforeach</select>
        <input type="number" name="base_fare" value="{{ $vehicle->base_fare }}" step="0.01" min="0" required class="input-dark">
        <input type="number" name="fare_per_km" value="{{ $vehicle->fare_per_km }}" step="0.01" min="0" required class="input-dark">
        <input type="datetime-local" name="next_available_at" value="{{ $vehicle->next_available_at?->format('Y-m-d\TH:i') }}" class="input-dark">
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="requires_quote" value="1" {{ $vehicle->requires_quote ? 'checked' : '' }}> Quote required</label>
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" {{ $vehicle->is_active ? 'checked' : '' }}> Active</label>
    </div>
    <div class="flex gap-4 mt-3"><button class="text-sky-400 text-sm">Save</button><button type="submit" form="delete-vehicle-{{ $vehicle->id }}" class="text-red-400 text-sm">Delete</button></div>
</form>
<form id="delete-vehicle-{{ $vehicle->id }}" method="POST" action="{{ route('vendor.vehicles.destroy', [$business->id, $vehicle->id]) }}" data-confirm="Delete this transport option?">@csrf @method('DELETE')</form>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No transport options yet. Add one above before customers can send requests.</div>
@endforelse
</div>
@endsection
