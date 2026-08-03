@extends('layouts.admin')

@section('title', 'Services')
@section('header', 'All Services')

@section('content')
<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search services..." class="input-dark w-full">
        </div>
        <div>
            <input type="text" name="business_id" value="{{ request('business_id') }}" placeholder="Business ID..." class="input-dark w-full">
        </div>
        <div>
            <select name="is_active" class="input-dark w-full">
                <option value="">All</option>
                <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary px-6 w-full">Filter</button>
        </div>
    </div>
    <div class="flex gap-2 mt-3">
        <a href="{{ route('admin.services') }}" class="btn-ghost">Clear</a>
        <span class="text-slate-500 text-sm self-center ml-2">{{ $services->total() }} services</span>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business</th>
                <th>Price</th>
                <th>Duration</th>
                <th>Bookings</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($services as $service)
                <tr>
                    <td class="font-medium">{{ $service->name }}</td>
                    <td class="text-sm">{{ $service->business->name ?? '-' }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($service->price, 2) }}</td>
                    <td class="text-sm">{{ $service->duration }} min</td>
                    <td class="text-sm">{{ $service->bookings_count ?? $service->bookings()->count() }}</td>
                    <td>
                        @if($service->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('admin.services.edit', $service->id) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                        <form method="POST" action="{{ route('admin.services.destroy', $service->id) }}" data-confirm="Delete this service?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No services found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($services->hasPages())
    <div class="mt-6">{{ $services->withQueryString()->links() }}</div>
@endif
@endsection
