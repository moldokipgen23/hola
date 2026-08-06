@extends('vendor.layouts.dashboard')
@if(isset($schedule))
    @section('title', 'Edit Departure')
    @section('header', 'Edit Departure')
@else
    @section('title', 'Add Departure')
    @section('header', 'Add Departure')
@endif
@section('content')
<div class="max-w-3xl mx-auto">
    @if(isset($schedule))
        <form method="POST" action="{{ route('vendor.schedules.update', [$business->id, $schedule->id]) }}">
            @csrf @method('PUT')
    @else
        <form method="POST" action="{{ route('vendor.schedules.store', $business->id) }}">
            @csrf
    @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-4">
                <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="glass-card p-6 rounded-xl space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Vehicle *</label>
                <select name="vehicle_id" class="input-dark" required>
                    <option value="">Choose a vehicle...</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ (old('vehicle_id') ?? $schedule->vehicle_id ?? null) == $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->name }} ({{ $vehicle->seats }} seats)
                        </option>
                    @endforeach
                </select>
                @if($vehicles->isEmpty())
                    <p class="text-xs text-amber-400 mt-2">Add a vehicle under Fleet & Options first.</p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Route (optional — pick a preset or type your own)</label>
                <select name="transport_route_id" class="input-dark mb-2">
                    <option value="">Custom route...</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}" {{ (old('transport_route_id') ?? $schedule->transport_route_id ?? null) == $route->id ? 'selected' : '' }}>{{ $route->origin }} → {{ $route->destination }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm text-slate-400 mb-1">Origin *</label><input type="text" name="origin" value="{{ old('origin', $schedule->origin ?? '') }}" required class="input-dark" placeholder="e.g. Lamka"></div>
                    <div><label class="block text-sm text-slate-400 mb-1">Destination *</label><input type="text" name="destination" value="{{ old('destination', $schedule->destination ?? '') }}" required class="input-dark" placeholder="e.g. Aizawl"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div><label class="block text-sm text-slate-400 mb-1">Departure date *</label><input type="date" name="departure_date" value="{{ old('departure_date', $schedule->departure_date->toDateString() ?? '') }}" min="{{ now()->toDateString() }}" required class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Departure time *</label><input type="time" name="departure_time" value="{{ old('departure_time', $schedule->departure_time ?? '') }}" required class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Seats for sale *</label><input type="number" name="seats_capacity" value="{{ old('seats_capacity', $schedule->seats_capacity ?? '') }}" min="1" max="100" required class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Price per seat (₹) *</label><input type="number" name="price" value="{{ old('price', $schedule->price ?? '') }}" step="0.01" min="0" required class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Distance (km)</label><input type="number" name="distance_km" value="{{ old('distance_km', $schedule->distance_km ?? '') }}" step="0.1" min="0" class="input-dark" placeholder="e.g. 200"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Travel time (hours)</label>
                    <input type="number" name="estimated_hours" value="{{ old('estimated_hours', isset($schedule) && $schedule->estimated_minutes ? round($schedule->estimated_minutes / 60, 1) : '') }}" step="0.5" min="0.5" max="48" class="input-dark" placeholder="e.g. 5">
                    <p class="text-xs text-slate-500 mt-1">Used to show arrival estimate.</p>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes', $schedule->notes ?? '') }}" class="input-dark" placeholder="Optional">
                </div>
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-2">Boarding stops (optional — pickup points like a bus app)</label>
                <div id="boarding-stops">
                    @forelse($schedule->boarding_stops ?? [] as $idx => $stop)
                        <div class="grid grid-cols-[1fr_120px_120px_36px] gap-2 mb-2 stop-row">
                            <input name="boarding_stops[{{ $idx }}][name]" value="{{ $stop['name'] }}" class="input-dark" placeholder="Stop name, e.g. Lamka Main Bus Stand">
                            <input name="boarding_stops[{{ $idx }}][time]" value="{{ $stop['time'] ?? '' }}" class="input-dark" type="time" placeholder="Time">
                            <input name="boarding_stops[{{ $idx }}][price_offset]" value="{{ $stop['price_offset'] ?? '' }}" class="input-dark" type="number" step="0.01" min="0" placeholder="+₹">
                            <button type="button" class="text-red-400 remove-stop">×</button>
                        </div>
                    @empty
                        <div class="grid grid-cols-[1fr_120px_120px_36px] gap-2 mb-2 stop-row">
                            <input name="boarding_stops[0][name]" class="input-dark" placeholder="Stop name, e.g. Lamka Main Bus Stand">
                            <input name="boarding_stops[0][time]" class="input-dark" type="time" placeholder="Time">
                            <input name="boarding_stops[0][price_offset]" class="input-dark" type="number" step="0.01" min="0" placeholder="+₹">
                            <button type="button" class="text-red-400 remove-stop">×</button>
                        </div>
                    @endforelse
                </div>
                <button type="button" id="add-boarding-stop" class="text-sky-400 text-sm">+ Add boarding stop</button>
            </div>

            <div>
                <label class="block text-sm text-slate-300 mb-2">Drop-off stops (optional)</label>
                <div id="drop-stops">
                    @forelse($schedule->drop_stops ?? [] as $idx => $stop)
                        <div class="grid grid-cols-[1fr_120px_120px_36px] gap-2 mb-2 stop-row">
                            <input name="drop_stops[{{ $idx }}][name]" value="{{ $stop['name'] }}" class="input-dark" placeholder="Stop name, e.g. Aizawl Central">
                            <input name="drop_stops[{{ $idx }}][time]" value="{{ $stop['time'] ?? '' }}" class="input-dark" type="time" placeholder="Time">
                            <input name="drop_stops[{{ $idx }}][price_offset]" value="{{ $stop['price_offset'] ?? '' }}" class="input-dark" type="number" step="0.01" min="0" placeholder="+₹">
                            <button type="button" class="text-red-400 remove-stop">×</button>
                        </div>
                    @empty
                        <div class="grid grid-cols-[1fr_120px_120px_36px] gap-2 mb-2 stop-row">
                            <input name="drop_stops[0][name]" class="input-dark" placeholder="Stop name, e.g. Aizawl Central">
                            <input name="drop_stops[0][time]" class="input-dark" type="time" placeholder="Time">
                            <input name="drop_stops[0][price_offset]" class="input-dark" type="number" step="0.01" min="0" placeholder="+₹">
                            <button type="button" class="text-red-400 remove-stop">×</button>
                        </div>
                    @endforelse
                </div>
                <button type="button" id="add-drop-stop" class="text-sky-400 text-sm">+ Add drop-off stop</button>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            @if(isset($schedule))
                <button type="submit" class="btn-primary">Update Departure</button>
            @else
                <button type="submit" class="btn-primary">Add Departure</button>
            @endif
            <a href="{{ route('vendor.schedules', $business->id) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function wireStopRows(containerId, addBtnId) {
    const container = document.getElementById(containerId);
    const addBtn = document.getElementById(addBtnId);
    addBtn.addEventListener('click', () => {
        const rows = container.querySelectorAll('.stop-row');
        const idx = rows.length;
        const row = rows[0].cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            input.value = '';
            const name = input.name.replace(/\[\d+\]/, '[' + idx + ']');
            input.name = name;
        });
        container.appendChild(row);
        row.querySelector('.remove-stop').addEventListener('click', () => row.remove());
    });
    container.querySelectorAll('.stop-row').forEach(row => {
        row.querySelector('.remove-stop').addEventListener('click', () => row.remove());
    });
}
wireStopRows('boarding-stops', 'add-boarding-stop');
wireStopRows('drop-stops', 'add-drop-stop');
</script>
@endpush
