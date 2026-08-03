@extends('vendor.layouts.dashboard')
@section('title', 'Time Slots')
@section('header', 'Time Slots')

@section('content')
@php $days = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday']; @endphp
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
    <div><h3 class="text-white font-semibold text-lg">{{ $service->name }}</h3><p class="text-slate-500 text-sm">{{ $service->booking_mode === 'seat' ? 'Capacity means seats available.' : 'Capacity means courts or units available at the same time.' }}</p></div>
    <a href="{{ route('vendor.services', $business->id) }}" class="btn-ghost">Back to Bookable Items</a>
</div>

<form method="POST" action="{{ route('vendor.services.slots.store', [$business->id, $service->id]) }}" class="glass-card p-5 rounded-xl mb-6">
    @csrf
    <h4 class="text-white font-medium mb-4">Add a weekly slot</h4>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <select name="day_of_week" class="input-dark"><option value="">Every day</option>@foreach($days as $number => $day)<option value="{{ $number }}">{{ $day }}</option>@endforeach</select>
        <input type="time" name="start_time" required class="input-dark" aria-label="Start time">
        <input type="time" name="end_time" required class="input-dark" aria-label="End time">
        <input type="number" name="capacity" value="{{ $service->booking_mode === 'seat' ? ($service->capacity ?? 1) : ($service->inventory_units ?? 1) }}" min="1" required class="input-dark" aria-label="Capacity">
        <div class="flex gap-2"><input type="number" name="price_override" step="0.01" min="0" class="input-dark" placeholder="Price override"><button class="btn-primary">Add</button></div>
    </div>
</form>

<div class="space-y-3">
@forelse($slots as $slot)
    <form method="POST" action="{{ route('vendor.services.slots.update', [$business->id, $service->id, $slot->id]) }}" class="glass-card p-4 rounded-xl">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 md:grid-cols-7 gap-3 items-center">
            <select name="day_of_week" class="input-dark"><option value="">Every day</option>@foreach($days as $number => $day)<option value="{{ $number }}" {{ $slot->day_of_week === $number ? 'selected' : '' }}>{{ $day }}</option>@endforeach</select>
            <input type="time" name="start_time" value="{{ substr($slot->start_time, 0, 5) }}" required class="input-dark">
            <input type="time" name="end_time" value="{{ substr($slot->end_time, 0, 5) }}" required class="input-dark">
            <input type="number" name="capacity" value="{{ $slot->capacity }}" min="1" required class="input-dark">
            <input type="number" name="price_override" value="{{ $slot->price_override }}" step="0.01" min="0" class="input-dark" placeholder="Base ₹{{ $service->price }}">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" {{ $slot->is_active ? 'checked' : '' }}> Active</label>
            <div class="flex gap-2"><button class="text-sky-400 text-sm">Save</button><button type="submit" form="delete-slot-{{ $slot->id }}" class="text-red-400 text-sm">Delete</button></div>
        </div>
    </form>
    <form id="delete-slot-{{ $slot->id }}" method="POST" action="{{ route('vendor.services.slots.destroy', [$business->id, $service->id, $slot->id]) }}" data-confirm="Delete this time slot?">@csrf @method('DELETE')</form>
@empty
    <div class="glass-card p-10 rounded-xl text-center text-slate-500">No time slots yet. Customers cannot book this item until at least one active slot is added.</div>
@endforelse
</div>
@endsection
