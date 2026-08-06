@extends('vendor.layouts.dashboard')

@section('title', 'Resources & Availability')
@section('header', 'Resources & Availability')

@section('content')
@php $days = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday']; @endphp
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">{{ $service->name }}</h3>
        <p class="text-slate-500 text-sm">Resources are the physical things customers book: courts, rooms, tables, chairs or service providers. Availability rules auto-generate booking slots with buffers and blackout dates.</p>
    </div>
    <a href="{{ route('vendor.services', $business->id) }}" class="btn-ghost">Back to Bookable Items</a>
</div>

@if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
@endif

<div class="glass-card p-5 rounded-xl mb-6">
    <h4 class="text-white font-medium mb-4">Add a resource</h4>
    <form method="POST" action="{{ route('vendor.services.resources.store', [$business->id, $service->id]) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        @csrf
        <input type="text" name="name" required placeholder="e.g. Court 1 / Deluxe Room / Chair 4" class="input-dark">
        <input type="text" name="resource_type" placeholder="Type (optional)" class="input-dark">
        <input type="number" name="capacity" value="1" min="1" required class="input-dark" aria-label="Capacity">
        <button class="btn-primary">Add Resource</button>
        <input type="text" name="description" placeholder="Description (optional)" class="input-dark md:col-span-4">
    </form>
</div>

@forelse($resources as $resource)
    <div class="glass-card p-5 rounded-xl mb-6">
        <form method="POST" action="{{ route('vendor.services.resources.update', [$business->id, $service->id, $resource->id]) }}" class="flex flex-col md:flex-row md:items-center gap-3 mb-4">
            @csrf @method('PUT')
            <input type="text" name="name" value="{{ $resource->name }}" required class="input-dark flex-1">
            <input type="text" name="resource_type" value="{{ $resource->resource_type }}" placeholder="Type" class="input-dark w-40">
            <input type="number" name="capacity" value="{{ $resource->capacity }}" min="1" class="input-dark w-28" aria-label="Capacity">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" {{ $resource->is_active ? 'checked' : '' }}> Active</label>
            <div class="flex gap-2">
                <button class="text-sky-400 text-sm">Save</button>
                <button type="submit" form="delete-resource-{{ $resource->id }}" class="text-red-400 text-sm">Delete</button>
            </div>
        </form>
        <form id="delete-resource-{{ $resource->id }}" method="POST" action="{{ route('vendor.services.resources.destroy', [$business->id, $service->id, $resource->id]) }}" data-confirm="Delete this resource and its availability rules?">@csrf @method('DELETE')</form>

        <div class="border-t border-white/10 pt-4">
            <div class="flex items-center justify-between mb-3">
                <h5 class="text-white font-medium text-sm">Availability rules</h5>
                <span class="text-slate-500 text-xs">{{ $resource->availabilityRules->count() }} rule(s)</span>
            </div>

            <form method="POST" action="{{ route('vendor.services.resources.rules.store', [$business->id, $service->id, $resource->id]) }}" class="grid grid-cols-1 md:grid-cols-5 gap-2 mb-4">
                @csrf
                <select name="day_of_week" class="input-dark">
                    <option value="">Every day</option>
                    @foreach($days as $number => $day)<option value="{{ $number }}">{{ $day }}</option>@endforeach
                </select>
                <input type="time" name="start_time" required class="input-dark" aria-label="Start">
                <input type="time" name="end_time" required class="input-dark" aria-label="End">
                <input type="number" name="slot_duration_minutes" value="{{ $service->duration ?? 60 }}" min="5" class="input-dark" aria-label="Slot mins">
                <button class="btn-primary">Add Rule</button>
                <input type="text" name="blackout_dates" placeholder="Blackout dates (2026-12-25, 2026-12-31)" class="input-dark md:col-span-2">
                <input type="number" name="buffer_minutes" value="0" min="0" class="input-dark" aria-label="Buffer mins" placeholder="Buffer min">
                <input type="number" name="capacity" placeholder="Capacity (defaults to resource)" class="input-dark" aria-label="Capacity">
                <input type="number" name="booking_window_days" value="30" min="1" class="input-dark" aria-label="Window days">
                <input type="number" name="minimum_notice_hours" value="0" min="0" class="input-dark" aria-label="Notice hours">
            </form>

            @forelse($resource->availabilityRules as $rule)
                <form method="POST" action="{{ route('vendor.services.resources.rules.update', [$business->id, $service->id, $resource->id, $rule->id]) }}" class="grid grid-cols-2 md:grid-cols-8 gap-2 items-center bg-white/5 p-3 rounded-lg mb-2">
                    @csrf @method('PUT')
                    <select name="day_of_week" class="input-dark">
                        <option value="" {{ $rule->day_of_week === null ? 'selected' : '' }}>Every day</option>
                        @foreach($days as $number => $day)<option value="{{ $number }}" {{ (string) $rule->day_of_week === (string) $number ? 'selected' : '' }}>{{ $day }}</option>@endforeach
                    </select>
                    <input type="time" name="start_time" value="{{ substr($rule->start_time, 0, 5) }}" class="input-dark">
                    <input type="time" name="end_time" value="{{ substr($rule->end_time, 0, 5) }}" class="input-dark">
                    <input type="number" name="slot_duration_minutes" value="{{ $rule->slot_duration_minutes }}" min="5" class="input-dark">
                    <input type="number" name="buffer_minutes" value="{{ $rule->buffer_minutes }}" min="0" class="input-dark" placeholder="Buffer">
                    <input type="number" name="capacity" value="{{ $rule->capacity }}" min="1" class="input-dark" placeholder="Capacity">
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_active" value="1" {{ $rule->is_active ? 'checked' : '' }}> Active</label>
                    <div class="flex gap-2">
                        <button class="text-sky-400 text-sm">Save</button>
                        <button type="submit" form="delete-rule-{{ $rule->id }}" class="text-red-400 text-sm">Del</button>
                    </div>
                    <div class="md:col-span-8">
                        <input type="text" name="blackout_dates" value="{{ implode(', ', $rule->blackout_dates ?? []) }}" placeholder="Blackout dates (comma separated)" class="input-dark w-full text-xs">
                        <div class="flex gap-4 mt-1 text-xs text-slate-400">
                            <label><input type="number" name="booking_window_days" value="{{ $rule->booking_window_days }}" min="1" class="input-dark w-24"> booking window (days)</label>
                            <label><input type="number" name="minimum_notice_hours" value="{{ $rule->minimum_notice_hours }}" min="0" class="input-dark w-24"> min notice (hrs)</label>
                        </div>
                    </div>
                </form>
                <form id="delete-rule-{{ $rule->id }}" method="POST" action="{{ route('vendor.services.resources.rules.destroy', [$business->id, $service->id, $resource->id, $rule->id]) }}" data-confirm="Delete this availability rule?">@csrf @method('DELETE')</form>
            @empty
                <p class="text-slate-500 text-xs">No rules yet. Add a rule to auto-generate bookable slots for this resource.</p>
            @endforelse
        </div>
    </div>
@empty
    <div class="glass-card p-10 rounded-xl text-center text-slate-500">
        No resources yet. Add courts, rooms, tables or providers to enable modern auto-generated availability.
        <div class="text-xs mt-2 text-slate-600">Without resources, this item falls back to classic weekly time slots.</div>
    </div>
@endforelse
@endsection
