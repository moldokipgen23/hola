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
                    <label class="block text-sm font-medium text-slate-400 mb-1">Pincode *</label>
                    <input type="text" name="pincode" value="{{ old('pincode', $business->pincode ?? '') }}" required maxlength="6"
                        class="input-dark" placeholder="e.g. 795128">
                    <p class="text-slate-500 text-xs mt-1">State and district are auto-derived from the pincode.</p>
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
            <div class="flex flex-wrap items-center gap-6">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $business->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $business->is_featured ?? 0) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Featured</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_bookable" value="1" {{ old('is_bookable', $business->is_bookable ?? 0) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Bookable</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Service Type</label>
                <select name="service_type" class="input-dark">
                    <option value="directory" {{ old('service_type', $business->service_type ?? '') === 'directory' ? 'selected' : '' }}>Directory (listing only)</option>
                    <option value="bookable" {{ old('service_type', $business->service_type ?? '') === 'bookable' ? 'selected' : '' }}>Bookable (services/appointments)</option>
                    <option value="buyable" {{ old('service_type', $business->service_type ?? '') === 'buyable' ? 'selected' : '' }}>Buyable (products/ordering)</option>
                    <option value="hybrid" {{ old('service_type', $business->service_type ?? '') === 'hybrid' ? 'selected' : '' }}>Hybrid (both)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Price Range (1-4)</label>
                <input type="number" name="price_range" value="{{ old('price_range', $business->price_range ?? '') }}" min="0" max="4" class="input-dark" placeholder="0 = unknown, 1-4 = $ to $$$$">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Delivery Radius (km)</label>
                <input type="number" name="delivery_radius_km" value="{{ old('delivery_radius_km', $business->delivery_radius_km ?? 5) }}" min="1" max="100" step="0.5" class="input-dark" placeholder="5">
                <p class="text-slate-500 text-xs mt-1">Maximum distance this business will deliver. Uses GPS-based distance check.</p>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h4 class="font-semibold text-slate-300 border-b border-white/5 pb-2">Payment Methods</h4>
            <p class="text-slate-500 text-xs mb-2">Select which payment gateways this vendor can accept. Leave all unchecked to use platform defaults.</p>
            @php $vendorMethods = old('payment_methods', !empty($business->payment_methods) ? $business->payment_methods : []); @endphp
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="payment_methods[razorpay]" value="0">
                    <input type="checkbox" name="payment_methods[razorpay]" value="razorpay" {{ in_array('razorpay', $vendorMethods) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Razorpay</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="payment_methods[cashfree]" value="0">
                    <input type="checkbox" name="payment_methods[cashfree]" value="cashfree" {{ in_array('cashfree', $vendorMethods) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Cashfree</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="payment_methods[cod]" value="0">
                    <input type="checkbox" name="payment_methods[cod]" value="cod" {{ in_array('cod', $vendorMethods) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Cash on Delivery</span>
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
