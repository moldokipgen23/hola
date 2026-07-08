@extends('layouts.admin')

@section('title', isset($product) ? 'Edit Product' : 'Add Product')
@section('header', isset($product) ? 'Edit Product' : 'Add Product')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ isset($product) ? route('admin.products.update', $product->id) : route('admin.products.store') }}">
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
                <label class="block text-sm font-medium text-slate-400 mb-1">Business *</label>
                <select name="business_id" required class="input-dark">
                    <option value="">Select...</option>
                    @foreach($businesses ?? [] as $biz)
                        <option value="{{ $biz->id }}" {{ old('business_id', $product->business_id ?? '') == $biz->id ? 'selected' : '' }}>
                            {{ $biz->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required
                    class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $product->slug ?? '') }}"
                    class="input-dark" placeholder="auto-generated if empty">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="3"
                    class="input-dark">{{ old('description', $product->description ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Price (₹)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Availability</label>
                    <select name="availability" class="input-dark">
                        <option value="in_stock" {{ old('availability', $product->availability ?? 'in_stock') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="out_of_stock" {{ old('availability', $product->availability ?? '') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                        <option value="pre_order" {{ old('availability', $product->availability ?? '') == 'pre_order' ? 'selected' : '' }}>Pre-Order</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active ?? 1) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-300">Active</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">
                {{ isset($product) ? 'Update' : 'Create' }}
            </button>
            <a href="{{ route('admin.products') }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
