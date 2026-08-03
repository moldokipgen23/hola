@extends('vendor.layouts.dashboard')

@section('title', 'Vehicles')
@section('header', 'Vehicles')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">All Vehicles</h3>
    <a href="{{ route('vendor.vehicles.create') }}" class="btn-primary">+ Add Vehicle</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Seats</th>
                <th>Base Fare</th>
                <th>Per KM</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vehicles ?? [] as $vehicle)
                <tr>
                    <td class="font-medium">{{ $vehicle->name }}</td>
                    <td class="text-sm capitalize">{{ $vehicle->type }}</td>
                    <td class="text-sm">{{ $vehicle->seats }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($vehicle->base_fare, 0) }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($vehicle->fare_per_km, 0) }}</td>
                    <td>
                        @if($vehicle->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('vendor.vehicles.edit', $vehicle->id) }}" class="text-purple-400 hover:text-purple-300">Edit</a>
                        <form method="POST" action="{{ route('vendor.vehicles.destroy', $vehicle->id) }}" data-confirm="Delete this vehicle?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No vehicles yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
