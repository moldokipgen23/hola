@extends('layouts.admin')

@section('title', 'Edit Transport Route')
@section('header', 'Edit Transport Route')

@section('content')
<div class="max-w-2xl mx-auto">
    <a href="{{ route('admin.transport-routes') }}" class="text-sky-400 text-sm hover:text-sky-300">&larr; Back to routes</a>

    <form method="POST" action="{{ route('admin.transport-routes.update', $route->id) }}" class="glass-card p-6 rounded-xl mt-4">
        @csrf @method('PUT')

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl mb-4">
                <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <h4 class="text-white font-medium mb-4">Route details</h4>
        <p class="text-slate-500 text-sm mb-4">The reverse direction is kept in sync automatically. Vendors still set their own price and travel time per departure — these are just suggestions.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1">Origin *</label>
                <input type="text" name="origin" value="{{ old('origin', $route->origin) }}" required class="input-dark">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Destination *</label>
                <input type="text" name="destination" value="{{ old('destination', $route->destination) }}" required class="input-dark">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Distance (km)</label>
                <input type="number" name="distance_km" value="{{ old('distance_km', $route->distance_km) }}" step="0.1" min="0.1" class="input-dark">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Travel time (minutes)</label>
                <input type="number" name="estimated_minutes" value="{{ old('estimated_minutes', $route->estimated_minutes) }}" min="1" class="input-dark">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Suggested price (₹)</label>
                <input type="number" name="base_fare" value="{{ old('base_fare', $route->base_fare) }}" step="0.01" min="0" class="input-dark">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $route->is_active) ? 'checked' : '' }}>
                    Active
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">Save Route</button>
            <a href="{{ route('admin.transport-routes') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
