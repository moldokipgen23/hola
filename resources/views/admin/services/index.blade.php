@extends('layouts.admin')

@section('title', 'Services')
@section('header', 'All Services')

@section('content')
<!-- Category type tabs (UrbanClap-style grouping) -->
<div class="flex gap-1 mb-4 border-b border-white/10 overflow-x-auto">
    <a href="{{ route('admin.services') }}"
        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ !request('category_id') && !request('booking_mode') ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
        All ({{ $services->total() }})
    </a>
    @foreach($groupCounts as $name => $count)
        @php
            $catId = \App\Models\Category::where('name', $name)->value('id');
        @endphp
        <a href="{{ route('admin.services', ['category_id' => $catId]) }}"
            class="px-4 py-2.5 text-sm font-medium whitespace-nowrap {{ request('category_id') == $catId ? 'text-white border-b-2 border-emerald-500' : 'text-slate-400 hover:text-white' }}">
            {{ $name }} ({{ $count }})
        </a>
    @endforeach
</div>

<form method="GET" class="glass-card p-4 rounded-xl mb-4">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search services..." class="input-dark w-full">
        </div>
        <div>
            <input type="text" name="business_id" value="{{ request('business_id') }}" placeholder="Business ID..." class="input-dark w-full">
        </div>
        <div>
            <select name="booking_mode" class="input-dark w-full">
                <option value="">All Modes</option>
                <option value="appointment" {{ request('booking_mode') == 'appointment' ? 'selected' : '' }}>Appointment</option>
                <option value="stay" {{ request('booking_mode') == 'stay' ? 'selected' : '' }}>Stay / Rooms</option>
                <option value="slot" {{ request('booking_mode') == 'slot' ? 'selected' : '' }}>Turf / Slot</option>
                <option value="seat" {{ request('booking_mode') == 'seat' ? 'selected' : '' }}>Seats / Events</option>
            </select>
        </div>
        <div>
            <select name="is_active" class="input-dark w-full">
                <option value="">All Status</option>
                <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary px-6">Filter</button>
            <a href="{{ route('admin.services') }}" class="btn-ghost">Clear</a>
        </div>
    </div>
</form>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Business</th>
                <th>Type</th>
                <th>Mode</th>
                <th>Price</th>
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
                    <td class="text-sm text-slate-400">{{ $service->business->category->name ?? 'Uncategorised' }}</td>
                    <td>
                        @php
                            $typeColors = [
                                'appointment' => 'bg-blue-500/20 text-blue-400',
                                'stay' => 'bg-purple-500/20 text-purple-400',
                                'slot' => 'bg-teal-500/20 text-teal-400',
                                'seat' => 'bg-amber-500/20 text-amber-400',
                            ];
                        @endphp
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $typeColors[$service->booking_mode] ?? 'bg-slate-500/20 text-slate-400' }}">
                            {{ ucfirst($service->booking_mode) }}
                        </span>
                    </td>
                    <td class="text-sm font-mono">₹{{ number_format($service->price, 2) }}/{{ $service->price_unit ?? 'booking' }}</td>
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
                <tr><td colspan="8" class="text-center text-slate-400 py-8">No services found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($services->hasPages())
    <div class="mt-6">{{ $services->withQueryString()->links() }}</div>
@endif
@endsection
