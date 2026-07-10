@extends('layouts.admin')

@section('title', isset($business) ? 'Edit Business' : 'Add Business')
@section('header', isset($business) ? 'Edit Business' : 'Add Business')

@section('content')
<div class="max-w-3xl mx-auto">
    <form method="POST" action="{{ isset($business) ? route('admin.businesses.update', $business->id) : route('admin.businesses.store') }}" enctype="multipart/form-data">
        @csrf
        @if(isset($business)) @method('PUT') @endif

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
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Basic Info</h4>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Business Name *</label>
                <input type="text" name="name" value="{{ old('name', $business->name ?? '') }}" required
                    class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $business->slug ?? '') }}"
                    class="input-dark" placeholder="auto-generated if empty">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Category *</label>
                    <select name="category_id" required class="input-dark">
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $business->category_id ?? '') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Subcategory</label>
                    <select name="subcategory_id" class="input-dark">
                        <option value="">None</option>
                        @foreach($subcategories as $subcategory)
                            <option value="{{ $subcategory->id }}" {{ old('subcategory_id', $business->subcategory_id ?? '') == $subcategory->id ? 'selected' : '' }}>
                                {{ $subcategory->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $business->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Contact & Location</h4>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Address *</label>
                <input type="text" name="address" value="{{ old('address', $business->address ?? '') }}" required
                    class="input-dark">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Locality</label>
                    <input type="text" name="locality" value="{{ old('locality', $business->locality ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">District</label>
                    <input type="text" name="district" value="{{ old('district', $business->district ?? 'Churachandpur') }}"
                        class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Latitude</label>
                    <input type="number" step="any" name="latitude" value="{{ old('latitude', $business->latitude ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Longitude</label>
                    <input type="number" step="any" name="longitude" value="{{ old('longitude', $business->longitude ?? '') }}"
                        class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $business->phone ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $business->whatsapp ?? '') }}"
                        class="input-dark">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $business->email ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website', $business->website ?? '') }}"
                        class="input-dark">
                </div>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Photos</h4>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Upload Photos</label>
                <input type="file" name="photos[]" multiple accept="image/*"
                    class="input-dark">
                @if(isset($business) && !empty($business->photos))
                    <div class="flex gap-2 mt-2">
                        @foreach($business->photos as $photo)
                            <img src="{{ str_starts_with($photo, 'http') ? $photo : asset($photo) }}" class="w-16 h-16 object-cover rounded">
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Options</h4>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $business->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $business->is_featured ?? 0) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Featured</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">
                {{ isset($business) ? 'Update' : 'Create' }}
            </button>
            <a href="{{ route('admin.businesses') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
