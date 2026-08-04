@extends('layouts.admin')

@section('title', 'Products')
@section('header', 'Products')

@section('content')
@php
    $filterQuery = request()->except(['category_id', 'page']);
@endphp

<div class="flex justify-between items-center mb-4">
    <div>
        <h3 class="text-white font-semibold text-lg">All Products</h3>
        <p class="text-slate-500 text-sm mt-1">{{ $products->total() }} products</p>
    </div>
    <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Add Product</a>
</div>

<!-- Category tabs -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    <a href="{{ route('admin.products', $filterQuery) }}"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ ! request('category_id') ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
        All ({{ $totalProducts }})
    </a>
    @foreach($categories as $cat)
        <a href="{{ route('admin.products', array_merge($filterQuery, ['category_id' => $cat->id])) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ request('category_id') == $cat->id ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $cat->name }} ({{ $cat->products_count }})
        </a>
    @endforeach
</div>

<!-- Search -->
<form method="GET" class="mb-4">
    <input type="hidden" name="category_id" value="{{ request('category_id') }}">
    <div class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..."
            class="input-dark w-full md:max-w-xs">
        <button type="submit" class="btn-primary">Search</button>
        @if(request('search') || request('category_id'))
            <a href="{{ route('admin.products') }}" class="btn-ghost">Clear</a>
        @endif
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business</th>
                <th>Category</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td class="font-medium">{{ $product->name }}</td>
                    <td class="text-sm">{{ $product->business->name ?? '-' }}</td>
                    <td>
                        @if($product->category)
                            <span class="badge badge-blue">{{ $product->category->name }}</span>
                        @else
                            <span class="text-slate-600 text-sm">—</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $product->price ? '₹' . number_format($product->price, 2) : '-' }}</td>
                    <td>
                        @if($product->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.products.edit', $product->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" data-confirm="Delete?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($products->hasPages())
    <div class="mt-4 text-slate-400">{{ $products->links() }}</div>
@endif
@endsection
