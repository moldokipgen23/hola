@extends('vendor.layouts.dashboard')

@section('title', isset($slot) ? 'Edit Time Slot' : 'Add Time Slot')
@section('header', isset($slot) ? 'Edit Time Slot' : 'Add Time Slot')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($slot) ? route('vendor.timeslots.update', $slot->id) : route('vendor.timeslots.store') }}">
        @csrf
        @if(isset($slot)) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <input type="hidden" name="service_id" value="{{ $service->id }}">
        <input type="hidden" name="business_id" value="{{ $business->id }}">

        <div class="glass-card p-6 rounded-lg space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Service</label>
                <p class="text-white font-medium">{{ $service->name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Day of Week</label>
                <select name="day_of_week" class="input-dark">
                    <option value="">All Days</option>
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $day)
                        <option value="{{ $i }}" {{ old('day_of_week', $slot->day_of_week ?? '') === (string)$i ? 'selected' : '' }}>{{ $day }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Start Time *</label>
                    <input type="time" name="start_time" value="{{ old('start_time', $slot->start_time ?? '06:00') }}" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">End Time *</label>
                    <input type="time" name="end_time" value="{{ old('end_time', $slot->end_time ?? '07:00') }}" required class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Capacity *</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $slot->capacity ?? '1') }}" min="1" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price Override (₹)</label>
                    <input type="number" name="price_override" value="{{ old('price_override', $slot->price_override ?? '') }}" step="0.01" min="0" class="input-dark" placeholder="Leave empty for service price">
                </div>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $slot->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">{{ isset($slot) ? 'Update' : 'Create' }}</button>
            <a href="{{ route('vendor.timeslots', $service->id) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
