@extends('vendor.layouts.dashboard')

@section('title', 'Products')
@section('header', 'Products')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Products</h3>
    <a href="{{ route('vendor.products.create', $business->id) }}" class="btn-primary">+ Add Product</a>
</div>

<div class="flex items-center justify-between mb-4">
    <p class="text-slate-400 text-sm">Business: <span class="text-white font-medium">{{ $business->name }}</span></p>
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
            <select name="status" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('vendor.products', $business->id) }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products ?? [] as $product)
                <tr>
                    <td class="font-medium">{{ $product->name }}</td>
                    <td class="text-sm">{{ $product->business->name ?? '-' }}</td>
                    <td class="text-sm">${{ number_format($product->price, 2) }}</td>
                    <td class="text-sm">{{ $product->stock ?? '-' }}</td>
                    <td>
                        @if($product->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('vendor.products.edit', ['businessId' => $business->id, 'id' => $product->id]) }}" class="text-purple-400 hover:text-purple-300">Edit</a>
                        <form method="POST" action="{{ route('vendor.products.destroy', ['businessId' => $business->id, 'id' => $product->id]) }}" data-confirm="Delete this product?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No products yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($products) && $products->hasPages())
    <div class="mt-4 text-slate-400">{{ $products->links() }}</div>
@endif
@endsection
