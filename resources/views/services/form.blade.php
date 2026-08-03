@extends('vendor.layouts.dashboard')

@section('title', isset($service) ? 'Edit Bookable Item' : 'Add Bookable Item')
@section('header', isset($service) ? 'Edit Bookable Item' : 'Add Bookable Item')

@section('content')
@php $mode = old('booking_mode', $service->booking_mode ?? 'appointment'); @endphp
<div class="max-w-3xl mx-auto">
    <form method="POST" action="{{ isset($service) ? route('vendor.services.update', ['businessId' => $business->id, 'id' => $service->id]) : route('vendor.services.store', $business->id) }}">
        @csrf
        @if(isset($service)) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-4">
                <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="glass-card p-6 rounded-xl space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">What are customers booking? *</label>
                <select name="booking_mode" id="booking-mode" class="input-dark" required>
                    <option value="appointment" {{ $mode === 'appointment' ? 'selected' : '' }}>Appointment or service</option>
                    <option value="slot" {{ $mode === 'slot' ? 'selected' : '' }}>Turf, court, venue or fixed time slot</option>
                    <option value="stay" {{ $mode === 'stay' ? 'selected' : '' }}>Hotel room, lodge or overnight stay</option>
                    <option value="seat" {{ $mode === 'seat' ? 'selected' : '' }}>Seat-based event, class or departure</option>
                </select>
                <p class="text-xs text-slate-500 mt-2" id="mode-help"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $service->name ?? '') }}" required class="input-dark" placeholder="e.g. Deluxe Room, Football Turf, Haircut">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $service->description ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price (₹) *</label>
                    <input type="number" name="price" value="{{ old('price', $service->price ?? '') }}" step="0.01" min="0" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price per *</label>
                    <select name="price_unit" class="input-dark">
                        @foreach(['booking' => 'Booking', 'hour' => 'Hour', 'night' => 'Night', 'person' => 'Person', 'seat' => 'Seat'] as $value => $label)
                            <option value="{{ $value }}" {{ old('price_unit', $service->price_unit ?? ($mode === 'stay' ? 'night' : 'booking')) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="non-stay-field">
                    <label class="block text-sm font-medium text-slate-400 mb-1">Duration (minutes)</label>
                    <input type="number" name="duration" value="{{ old('duration', $service->duration ?? 60) }}" min="15" max="1440" class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1" id="capacity-label">People capacity</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $service->capacity ?? 1) }}" min="1" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1" id="inventory-label">Available units</label>
                    <input type="number" name="inventory_units" value="{{ old('inventory_units', $service->inventory_units ?? 1) }}" min="1" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Unit name</label>
                    <input type="text" name="unit_label" value="{{ old('unit_label', $service->unit_label ?? '') }}" maxlength="40" class="input-dark" placeholder="room, court, chair">
                </div>
            </div>

            <div id="stay-fields" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div><label class="block text-sm text-slate-400 mb-1">Check-in</label><input type="time" name="check_in_time" value="{{ old('check_in_time', $service->check_in_time ?? '14:00') }}" class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Check-out</label><input type="time" name="check_out_time" value="{{ old('check_out_time', $service->check_out_time ?? '11:00') }}" class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Minimum nights</label><input type="number" name="min_stay_nights" value="{{ old('min_stay_nights', $service->min_stay_nights ?? 1) }}" min="1" class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Maximum nights</label><input type="number" name="max_stay_nights" value="{{ old('max_stay_nights', $service->max_stay_nights ?? '') }}" min="1" class="input-dark" placeholder="Optional"></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm text-slate-400 mb-1">Book up to (days ahead)</label><input type="number" name="advance_booking_days" value="{{ old('advance_booking_days', $service->advance_booking_days ?? 60) }}" min="1" max="365" class="input-dark"></div>
                <div><label class="block text-sm text-slate-400 mb-1">Cancellation notice (hours)</label><input type="number" name="cancellation_hours" value="{{ old('cancellation_hours', $service->cancellation_hours ?? 2) }}" min="0" max="168" class="input-dark"></div>
            </div>

            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? 1) ? 'checked' : '' }}><span class="text-sm text-slate-300">Active and visible to customers</span></label>
        </div>

        <div class="mt-6 flex gap-2"><button type="submit" class="btn-primary">{{ isset($service) ? 'Save Changes' : 'Create' }}</button><a href="{{ route('vendor.services', $business->id) }}" class="btn-ghost">Cancel</a></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const modeSelect = document.getElementById('booking-mode');
function syncBookingMode() {
    const mode = modeSelect.value;
    document.getElementById('stay-fields').style.display = mode === 'stay' ? 'grid' : 'none';
    document.querySelectorAll('.non-stay-field').forEach(el => el.style.display = mode === 'stay' ? 'none' : 'block');
    document.getElementById('capacity-label').textContent = mode === 'seat' ? 'Total seats per slot' : 'People capacity';
    document.getElementById('inventory-label').textContent = mode === 'stay' ? 'Number of rooms / units' : (mode === 'slot' ? 'Courts / units available' : 'Simultaneous units');
    document.getElementById('mode-help').textContent = {
        appointment: 'Customer chooses a date and start time. Capacity prevents overlapping requests.',
        slot: 'Create fixed weekly slots after saving. Each slot controls simultaneous courts or units.',
        stay: 'Customer chooses check-in, check-out, rooms and guests. Inventory is protected for every night.',
        seat: 'Create fixed slots with seat capacity. Customers can request seat numbers.'
    }[mode];
}
modeSelect.addEventListener('change', syncBookingMode); syncBookingMode();
</script>
@endpush
