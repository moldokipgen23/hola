@extends('vendor.layouts.dashboard')

@section('title', 'Services')
@section('header', 'Services')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Services</h3>
    <a href="{{ route('vendor.services.create') }}" class="btn-primary">+ Add Service</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Status</th>
                <th>Bookings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($services ?? [] as $service)
                <tr>
                    <td class="font-medium">{{ $service->name }}</td>
                    <td class="text-sm">{{ $service->duration }} min</td>
                    <td class="text-sm font-mono">${{ number_format($service->price, 2) }}</td>
                    <td>
                        @if($service->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $service->bookings_count ?? $service->bookings()->count() }}</td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('vendor.services.edit', $service->id) }}" class="text-purple-400 hover:text-purple-300">Edit</a>
                        <form method="POST" action="{{ route('vendor.services.destroy', $service->id) }}" data-confirm="Delete this service?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No services yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(isset($services) && $services->hasPages())
    <div class="mt-4 text-slate-400">{{ $services->links() }}</div>
@endif
@endsection
