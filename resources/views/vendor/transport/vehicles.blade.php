@extends('vendor.layouts.dashboard')
@section('title', 'Fleet & Transport Options')
@section('header', 'Fleet & Transport Options')
@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">{{ $business->name }}</h3>
        <p class="text-slate-500 text-sm">Add taxis, shared vehicles, rentals, trucks, and goods carriers.</p>
    </div>
    <a href="{{ route('vendor.trips', $business->id) }}" class="btn-primary">View Requests</a>
</div>

<form method="POST" action="{{ route('vendor.vehicles.store', $business->id) }}" class="glass-card p-5 rounded-xl mb-6">
    @csrf
    <h4 class="text-white font-medium mb-4">Add transport option</h4>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <input name="name" required class="input-dark" placeholder="Vehicle name">
        <select name="type" class="input-dark" required>
            @forelse($vehicleTypes ?? [] as $type)
                <option value="{{ $type->slug }}">{{ $type->name }}</option>
            @empty
                <option value="">No vehicle types configured</option>
            @endforelse
        </select>
        <select name="service_mode" class="input-dark"><option value="taxi">Taxi / ride</option><option value="shared">Shared seats</option><option value="bus">Bus / intercity</option><option value="rental">Rental</option><option value="goods">Truck / goods</option></select>
        <input type="number" name="seats" value="4" min="1" required class="input-dark" placeholder="Seats">
        <div class="flex"><input type="number" name="capacity_value" step="0.01" min="0" class="input-dark" placeholder="Load capacity"><select name="capacity_unit" class="input-dark"><option value="seats">seats</option><option value="kg">kg</option><option value="tons">tons</option><option value="vehicle">vehicle</option></select></div>
        <input type="number" name="base_fare" value="0" step="0.01" min="0" required class="input-dark" placeholder="Base fare ₹">
        <input type="number" name="fare_per_km" value="0" step="0.01" min="0" required class="input-dark" placeholder="₹ per km">
        <input type="number" name="price_per_day" step="0.01" min="0" class="input-dark" placeholder="₹ per day (hire)">
        <input type="number" name="min_km" value="1" min="1" class="input-dark" placeholder="Minimum km">
        <input name="registration_number" class="input-dark" placeholder="Registration (private)">
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="requires_quote" value="1"> Always request quote</label>
    </div>
    <div class="mt-4">
        <label class="block text-sm text-slate-400 mb-1">Visual seat layout (optional — RedBus / aeroplane style)</label>
        <textarea name="seat_layout_json" rows="3" class="input-dark font-mono" placeholder='JSON, e.g. [{"label":"A1","row":1,"col":1,"deck":"lower","type":"window"},{"label":"A2","row":1,"col":2,"deck":"lower","type":"aisle"}]'></textarea>
        <p class="text-xs text-slate-500 mt-1">Leave empty to use an automatic 2-2 numbered grid based on seat count.</p>
    </div>
    <div class="mt-4">
        <label class="block text-sm text-slate-400 mb-1">Hire terms & conditions (shown to customers when renting this vehicle)</label>
        <textarea name="terms" rows="3" class="input-dark" placeholder="e.g. Security deposit ₹1000 · Fuel excluded · 100 km/day included · Driver available on request · Late return ₹100/hr"></textarea>
    </div>
    <button class="btn-primary mt-4">Add Transport Option</button>
</form>

<div class="space-y-3">
@forelse($vehicles as $vehicle)
<form method="POST" action="{{ route('vendor.vehicles.update', [$business->id, $vehicle->id]) }}" class="glass-card p-4 rounded-xl">@csrf @method('PUT')
    <div class="flex justify-between mb-3">
        <div>
            <span class="text-white font-medium">{{ $vehicle->name }}</span>
            <span class="badge badge-blue ml-2">{{ ucfirst($vehicle->service_mode) }}</span>
            @if($vehicle->registration_number)
                <span class="text-xs text-slate-500 ml-2">{{ $vehicle->registration_number }}</span>
            @endif
            @if($vehicle->hasVisualSeatLayout())
                <span class="badge badge-purple ml-2">Seat map</span>
            @endif
        </div>
        <span class="text-xs text-slate-500">{{ $vehicle->trips_count }} active request(s)</span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <input name="name" value="{{ $vehicle->name }}" required class="input-dark">
        <select name="type" class="input-dark">
            @forelse($vehicleTypes ?? [] as $type)
                <option value="{{ $type->slug }}" {{ $vehicle->type === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
            @empty
                <option value="{{ $vehicle->type }}">{{ ucfirst($vehicle->type) }}</option>
            @endforelse
        </select>
        <select name="service_mode" class="input-dark">@foreach(['taxi','shared','bus','rental','goods'] as $mode)<option value="{{ $mode }}" {{ $vehicle->service_mode === $mode ? 'selected' : '' }}>{{ ucfirst($mode) }}</option>@endforeach</select>
        <input type="number" name="seats" value="{{ $vehicle->seats }}" min="1" required class="input-dark">
        <div class="flex"><input type="number" name="capacity_value" value="{{ $vehicle->capacity_value }}" step="0.01" class="input-dark"><select name="capacity_unit" class="input-dark">@foreach(['seats','kg','tons','vehicle'] as $unit)<option {{ $vehicle->capacity_unit === $unit ? 'selected' : '' }}>{{ $unit }}</option>@endforeach</select></div>
        <select name="availability_status" class="input-dark" onchange="toggleVehicleAvailability({{ $vehicle->id }}, this.value)">
            @foreach(['available','busy','offline'] as $status)
                <option value="{{ $status }}" {{ $vehicle->availability_status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <input type="number" name="base_fare" value="{{ $vehicle->base_fare }}" step="0.01" min="0" required class="input-dark">
        <input type="number" name="fare_per_km" value="{{ $vehicle->fare_per_km }}" step="0.01" min="0" required class="input-dark">
        <input type="number" name="price_per_day" value="{{ $vehicle->price_per_day }}" step="0.01" min="0" class="input-dark" placeholder="₹ per day (hire)">
        <input type="datetime-local" name="next_available_at" value="{{ $vehicle->next_available_at?->format('Y-m-d\TH:i') }}" class="input-dark">
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="requires_quote" value="1" {{ $vehicle->requires_quote ? 'checked' : '' }}> Quote required</label>
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" {{ $vehicle->is_active ? 'checked' : '' }}> Active</label>
    </div>
    <div class="mt-3">
        <label class="block text-sm text-slate-400 mb-1">Visual seat layout</label>
        <textarea name="seat_layout_json" rows="3" class="input-dark font-mono" placeholder='[{"label":"A1","row":1,"col":1,"deck":"lower","type":"window"}]'>{{ $vehicle->hasVisualSeatLayout() ? json_encode($vehicle->seat_layout) : '' }}</textarea>
    </div>
    <div class="mt-3">
        <label class="block text-sm text-slate-400 mb-1">Hire terms & conditions</label>
        <textarea name="terms" rows="3" class="input-dark" placeholder="e.g. Deposit, fuel, km/day, driver, late return">{{ $vehicle->terms }}</textarea>
    </div>
    <div class="flex gap-4 mt-3">
        <button class="text-sky-400 text-sm">Save</button>
        <button type="button" form="avail-{{ $vehicle->id }}" class="text-xs text-slate-400">Quick availability →</button>
        <button type="submit" form="delete-vehicle-{{ $vehicle->id }}" class="text-red-400 text-sm">Delete</button>
    </div>
</form>
<form id="delete-vehicle-{{ $vehicle->id }}" method="POST" action="{{ route('vendor.vehicles.destroy', [$business->id, $vehicle->id]) }}" data-confirm="Delete this transport option?">@csrf @method('DELETE')</form>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No transport options yet. Add one above before customers can send requests.</div>
@endforelse
</div>
@endsection

@push('scripts')
<script>
function toggleVehicleAvailability(vehicleId, status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/vendor/businesses/{{ $business->id }}/vehicles/${vehicleId}/availability`;
    form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PUT"><input type="hidden" name="availability_status" value="' + status + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
