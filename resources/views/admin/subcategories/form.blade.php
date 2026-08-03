@extends('layouts.admin')

@section('title', isset($subcategory) ? 'Edit Business Type' : 'Add Business Type')
@section('header', isset($subcategory) ? 'Edit Business Type' : 'Add Business Type')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($subcategory) ? route('admin.subcategories.update', $subcategory->id) : route('admin.subcategories.store') }}">
        @csrf
        @if(isset($subcategory)) @method('PUT') @endif

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
                <label class="block text-sm font-medium text-slate-400 mb-1">Category *</label>
                <select name="category_id" required class="input-dark">
                    <option value="">Select...</option>
                    @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $subcategory->category_id ?? request('category_id', '')) == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business type *</label>
                <input type="text" name="name" value="{{ old('name', $subcategory->name ?? '') }}" required
                    class="input-dark" placeholder="Example: Taxi, Football Turf, Restaurant">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $subcategory->slug ?? '') }}"
                    class="input-dark" placeholder="auto-generated if empty">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Icon</label>
                <input type="text" name="icon" value="{{ old('icon', $subcategory->icon ?? '') }}"
                    class="input-dark" placeholder="e.g., 🍕">
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $subcategory->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-2">What should this business be able to do?</label>
                <p class="text-xs text-slate-500 mb-3">Choose only what applies. Every business already has a listing, map, call, and WhatsApp details.</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach([
                        'catalog' => 'Show products or menu',
                        'orders' => 'Accept COD orders',
                        'bookings' => 'Accept booking requests',
                        'inventory' => 'Track product availability',
                        'transport' => 'Accept taxi / transport requests',
                        'turf' => 'Manage turf time slots',
                    ] as $module => $label)
                        <label class="flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2">
                            <input type="checkbox" name="recommended_modules[]" value="{{ $module }}"
                                {{ in_array($module, old('recommended_modules', $subcategory->recommended_modules ?? []), true) ? 'checked' : '' }}>
                            <span class="text-sm text-slate-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Order</label>
                <input type="number" name="order" value="{{ old('order', $subcategory->order ?? 0) }}"
                    class="input-dark">
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">
                {{ isset($subcategory) ? 'Update' : 'Create' }}
            </button>
            <a href="{{ route('admin.subcategories') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
