@extends('layouts.admin')

@section('title', 'Transport Routes')
@section('header', 'Transport Routes')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Transport Routes</h3>
        <p class="text-slate-500 text-sm mt-1">Curated origin ↔ destination routes vendors can pick from. Adding a route creates both directions automatically.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.transport-routes.store') }}" class="glass-card p-5 rounded-xl mb-6">
    @csrf
    <h4 class="text-white font-medium mb-4">Add route suggestion</h4>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <input name="origin" required class="input-dark" placeholder="Origin (e.g. Lamka)">
        <input name="destination" required class="input-dark" placeholder="Destination (e.g. Aizawl)">
        <input type="number" name="distance_km" step="0.1" min="0.1" class="input-dark" placeholder="Distance (km)">
        <input type="number" name="base_fare" step="0.01" min="0" class="input-dark" placeholder="Suggested ₹ (optional)">
        <button class="btn-primary">Add Route</button>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr><th>Route</th><th>Distance</th><th>Base Fare</th><th>Departures</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($routes as $route)
            <tr>
                <td class="font-medium text-white">{{ $route->origin }} → {{ $route->destination }}</td>
                <td class="text-sm">{{ $route->distance_km ? $route->distance_km.' km' : '—' }}</td>
                <td class="text-sm">{{ $route->base_fare ? '₹'.number_format($route->base_fare, 2) : '—' }}</td>
                <td class="text-sm">{{ $route->schedules_count }}</td>
                <td>
                    @if($route->is_active)
                        <span class="badge badge-green">Active</span>
                    @else
                        <span class="badge badge-red">Inactive</span>
                    @endif
                </td>
                <td class="text-sm space-x-2">
                    <a href="{{ route('admin.transport-routes.edit', $route->id) }}" class="text-sky-400 hover:text-sky-300">Edit</a>
                    <form method="POST" action="{{ route('admin.transport-routes.toggle', $route->id) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-blue-400 hover:text-blue-300">{{ $route->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                    @if($route->schedules_count === 0)
                    <form method="POST" action="{{ route('admin.transport-routes.destroy', $route->id) }}" data-confirm="Delete this route?" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-slate-500 py-8">No routes yet. Add one so vendors can pick it for departures.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $routes->links() }}</div>
@endsection
