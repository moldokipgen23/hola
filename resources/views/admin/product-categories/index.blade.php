@extends('layouts.admin')

@section('title', 'Product Categories')
@section('header', 'Product Categories')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-slate-400 text-sm">Per-business storefront categories, grouped under Shop sections.</p>
            <p class="text-slate-500 text-xs mt-1">Only shopping businesses appear here. Pick one to manage its product categories.</p>
        </div>
    </div>

    <!-- Business picker -->
    <form method="GET" action="{{ route('admin.product-categories') }}" class="glass-card p-4 rounded-xl mb-6">
        <label class="block text-sm text-slate-400 mb-1">Business</label>
        <select name="business_id" class="input-dark" onchange="this.form.submit()">
            @foreach($businesses as $biz)
                <option value="{{ $biz->id }}" {{ $business && $business->id === $biz->id ? 'selected' : '' }}>
                    {{ $biz->name }}
                </option>
            @endforeach
        </select>
    </form>

    @if(! $business)
        <div class="glass-card p-4 rounded-lg text-center">
            <p class="text-slate-500 text-sm">No businesses yet. Add a business first.</p>
        </div>
    @else
        <!-- Add category -->
        <div class="glass-card p-5 rounded-xl mb-6">
            <h3 class="text-white font-semibold mb-1">Add Product Category</h3>
            <p class="text-slate-500 text-xs mb-4">For: <span class="text-slate-300">{{ $business->name }}</span></p>
            <form method="POST" action="{{ route('admin.product-categories.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                @csrf
                <input type="hidden" name="business_id" value="{{ $business->id }}">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Name</label>
                    <input type="text" name="name" required class="input-dark" placeholder="e.g. Fruits, Starters">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Shop Section</label>
                    <select name="shop_section_id" class="input-dark">
                        <option value="">None</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Parent Category</label>
                    <select name="parent_id" class="input-dark">
                        <option value="">None (top level)</option>
                        @foreach($categories->whereNull('parent_id') as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">Add</button>
            </form>
        </div>

        <!-- List -->
        <div class="glass-card rounded-xl overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Shop Section</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="font-medium">{{ $category->name }}</td>
                            <td>
                                @if($category->section)
                                    <span class="badge badge-blue">{{ $category->section->name }}</span>
                                @else
                                    <span class="text-slate-500 text-sm">—</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $category->products_count }}</td>
                            <td>
                                @if($category->is_active)
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-red">Inactive</span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('admin.product-categories.update', $category->id) }}" class="flex items-center gap-2">
                                        @csrf @method('PATCH')
                                        <input type="text" name="name" value="{{ $category->name }}" class="input-dark !py-1.5 text-sm w-36" required>
                                        <select name="shop_section_id" class="input-dark !py-1.5 text-sm">
                                            <option value="">No section</option>
                                            @foreach($sections as $section)
                                                <option value="{{ $section->id }}" {{ $category->shop_section_id === $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                                            @endforeach
                                        </select>
                                        <label class="flex items-center gap-1 text-xs text-slate-400">
                                            <input type="checkbox" name="is_active" value="1" {{ $category->is_active ? 'checked' : '' }} class="rounded">
                                            Active
                                        </label>
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 text-xs">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.product-categories.destroy', $category->id) }}" data-confirm="Delete this product category?" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 py-8">No product categories yet for {{ $business->name }}.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
