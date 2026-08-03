@extends('layouts.admin')

@section('title', 'Products')
@section('header', 'Products')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Products</h3>
    <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Add Product</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products ?? [] as $product)
                <tr>
                    <td class="font-medium">{{ $product->name }}</td>
                    <td class="text-sm">{{ $product->business->name ?? '-' }}</td>
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
                <tr><td colspan="5" class="text-center text-slate-400">No products yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($products) && $products->hasPages())
    <div class="mt-4 text-slate-400">{{ $products->links() }}</div>
@endif
@endsection
