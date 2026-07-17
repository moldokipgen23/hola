@extends('layouts.admin')

@section('title', isset($area) ? 'Edit Area' : 'Add Area')
@section('header', isset($area) ? 'Edit Area' : 'Add Area')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($area) ? route('admin.areas.update', $area->id) : route('admin.areas.store') }}">
        @csrf
        @if(isset($area)) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="glass-card p-6 rounded-lg space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $area->name ?? '') }}" required
                    class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $area->slug ?? '') }}"
                    class="input-dark" placeholder="auto-generated if empty">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">District *</label>
                <input type="text" name="district" value="{{ old('district', $area->district ?? '') }}" required
                    class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">State *</label>
                <input type="text" name="state" value="{{ old('state', $area->state ?? '') }}" required
                    class="input-dark">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Latitude</label>
                    <input type="text" name="latitude" step="any" value="{{ old('latitude', $area->latitude ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Longitude</label>
                    <input type="text" name="longitude" step="any" value="{{ old('longitude', $area->longitude ?? '') }}"
                        class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Bounds</label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">North</label>
                        <input type="text" name="bounds_north" step="any" value="{{ old('bounds_north', $area->bounds_north ?? '') }}"
                            class="input-dark">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">South</label>
                        <input type="text" name="bounds_south" step="any" value="{{ old('bounds_south', $area->bounds_south ?? '') }}"
                            class="input-dark">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">East</label>
                        <input type="text" name="bounds_east" step="any" value="{{ old('bounds_east', $area->bounds_east ?? '') }}"
                            class="input-dark">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">West</label>
                        <input type="text" name="bounds_west" step="any" value="{{ old('bounds_west', $area->bounds_west ?? '') }}"
                            class="input-dark">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Pincodes (one per line)</label>
                <textarea name="pincodes" rows="4" class="input-dark w-full" placeholder="110001&#10;110002">{{ old('pincodes', isset($area) && $area->pincodes ? (is_array($area->pincodes) ? implode("\n", $area->pincodes) : $area->pincodes) : '') }}</textarea>
                <p class="text-slate-500 text-xs mt-1">Enter pincodes covered by this area, one per line.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Order</label>
                <input type="number" name="order" value="{{ old('order', $area->order ?? 0) }}"
                    class="input-dark">
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $area->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">
                {{ isset($area) ? 'Update' : 'Create' }}
            </button>
            <a href="{{ route('admin.areas') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
