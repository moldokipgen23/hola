@extends('layouts.admin')

@section('title', 'Vehicle Types')
@section('header', 'Vehicle Types')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-6">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-slate-400 text-sm">Global transport types for the Ride department (e.g. Car, Truck, Bus, Auto).</p>
            <p class="text-slate-500 text-xs mt-1">Vendors pick from these when adding a transport option. Deactivating a type hides it from vendors.</p>
        </div>
    </div>

    <!-- Add Type -->
    <div class="glass-card p-5 rounded-xl mb-6">
        <h3 class="text-white font-semibold mb-4">Add Vehicle Type</h3>
        <form method="POST" action="{{ route('admin.vehicle-types.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm text-slate-400 mb-1">Name</label>
                <input type="text" name="name" required class="input-dark" placeholder="e.g. Truck, Tempo, Minivan">
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm text-slate-400 mb-1">Slug (optional)</label>
                <input type="text" name="slug" class="input-dark" placeholder="e.g. truck">
            </div>
            <div class="flex-1 min-w-[220px]">
                <label class="block text-sm text-slate-400 mb-1">Description</label>
                <input type="text" name="description" class="input-dark" placeholder="What is this vehicle type?">
            </div>
            <div class="w-28">
                <label class="block text-sm text-slate-400 mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="0" min="0" class="input-dark">
            </div>
            <button type="submit" class="btn-primary">Add</button>
        </form>
    </div>

    <!-- Types -->
    <div class="glass-card rounded-xl overflow-hidden">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Vehicles</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $type)
                    <tr>
                        <td class="font-medium">{{ $type->name }}</td>
                        <td class="text-sm">{{ $type->description ?? '-' }}</td>
                        <td class="text-sm">{{ $type->vehicles_count }}</td>
                        <td>
                            @if($type->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-red">Inactive</span>
                            @endif
                        </td>
                        <td class="text-sm">
                            <div class="flex items-center gap-3">
                                <form method="POST" action="{{ route('admin.vehicle-types.toggle', $type->id) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-sm text-slate-400 hover:text-white">{{ $type->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.vehicle-types.destroy', $type->id) }}" class="inline" data-confirm="Delete this vehicle type?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm text-red-400 hover:text-red-300">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-slate-400 py-8">No vehicle types yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
