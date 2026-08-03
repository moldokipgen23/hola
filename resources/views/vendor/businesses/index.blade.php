@extends('vendor.layouts.dashboard')

@section('title', 'My Businesses')
@section('header', 'My Businesses')

@section('content')
<div class="glass-card rounded-lg overflow-hidden">
    <div class="flex items-center justify-between p-5 border-b border-white/5">
        <h3 class="text-white font-semibold">Your businesses</h3>
        <a href="{{ route('vendor.businesses.create') }}" class="btn-primary text-sm px-4 py-2">+ Add business</a>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Status</th>
                <th>Rating</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($businesses ?? [] as $business)
                <tr>
                    <td class="font-medium">
                        <div class="text-white">{{ $business->name }}</div>
                        <div class="text-xs text-slate-500">{{ $business->address }}</div>
                    </td>
                    <td class="text-sm">{{ $business->category->name ?? '-' }}</td>
                    <td>
                        @if($business->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        @if($business->avg_rating)
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <span>{{ number_format($business->avg_rating, 1) }}</span>
                            </div>
                        @else
                            <span class="text-slate-500">&mdash;</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        <div class="flex gap-3 flex-wrap">
                            <a href="{{ route('vendor.businesses.edit', $business->id) }}" class="text-purple-400 hover:text-purple-300 font-medium">Edit</a>
                            <a href="{{ route('vendor.businesses.modules', $business->id) }}" class="text-sky-400 hover:text-sky-300 font-medium">Features</a>
                            @if($business->hasModule('catalog'))<a href="{{ route('vendor.products', $business->id) }}" class="text-emerald-400 hover:text-emerald-300 font-medium">Products</a>@endif
                            @if($business->hasModule('bookings'))<a href="{{ route('vendor.services', $business->id) }}" class="text-amber-400 hover:text-amber-300 font-medium">Bookable Items</a>@endif
                            @if($business->hasModule('transport'))<a href="{{ route('vendor.vehicles', $business->id) }}" class="text-cyan-400 hover:text-cyan-300 font-medium">Transport</a>@endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500 py-8">No businesses found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($businesses ?? collect(), 'links'))
    <div class="mt-4 text-slate-400">{{ $businesses->links() }}</div>
@endif
@endsection
