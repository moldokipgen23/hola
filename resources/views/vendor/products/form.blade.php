@extends('vendor.layouts.dashboard')

@section('title', isset($product) ? 'Edit Product' : 'Add Product')
@section('header', isset($product) ? 'Edit Product' : 'Add Product')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($product) ? route('vendor.products.update', ['businessId' => $business->id, 'id' => $product->id]) : route('vendor.products.store', $business->id) }}" enctype="multipart/form-data">
        @csrf
        @if(isset($product)) @method('PUT') @endif

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
                <label class="block text-sm font-medium text-slate-400 mb-1">Business</label>
                <input type="text" value="{{ $business->name }}" disabled class="input-dark opacity-60">
                <input type="hidden" name="business_id" value="{{ $business->id }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3" class="input-dark">{{ old('description', $product->description ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price ($)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Stock</label>
                    <input type="number" name="stock" value="{{ old('stock', $product->stock ?? '') }}" class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Image</label>
                <input type="file" name="image" accept="image/*" class="input-dark">
                @if(isset($product) && $product->image)
                    <div class="mt-2">
                        <img src="{{ str_starts_with($product->image, 'http') ? $product->image : asset($product->image) }}"
                             class="w-20 h-20 object-cover rounded-lg border border-white/5">
                    </div>
                @endif
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active ?? 1) ? 'checked' : '' }}>
                <span class="text-sm text-slate-300">Active</span>
            </label>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">{{ isset($product) ? 'Update' : 'Create' }}</button>
            <a href="{{ route('vendor.products', $business->id) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
