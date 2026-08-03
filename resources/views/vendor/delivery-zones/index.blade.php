@extends('vendor.layouts.dashboard')

@section('title', 'Delivery Zones')
@section('header', 'Delivery Zones')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Delivery Zones</h3>
    <a href="{{ route('vendor.delivery-zones.create') }}" class="btn-primary">+ Add Zone</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Area</th>
                <th>Delivery Fee</th>
                <th>Min Order</th>
                <th>Est. Minutes</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($zones ?? [] as $zone)
                <tr>
                    <td class="font-medium">{{ $zone->area->name ?? 'Unknown' }}</td>
                    <td class="text-sm font-mono">₹{{ number_format($zone->delivery_fee, 0) }}</td>
                    <td class="text-sm">{{ $zone->min_order_amount ? '₹'.number_format($zone->min_order_amount, 0) : '-' }}</td>
                    <td class="text-sm">{{ $zone->estimated_minutes ? $zone->estimated_minutes.' min' : '-' }}</td>
                    <td>
                        @if($zone->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('vendor.delivery-zones.edit', $zone->id) }}" class="text-purple-400 hover:text-purple-300">Edit</a>
                        <form method="POST" action="{{ route('vendor.delivery-zones.destroy', $zone->id) }}" data-confirm="Delete this delivery zone?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No delivery zones yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
