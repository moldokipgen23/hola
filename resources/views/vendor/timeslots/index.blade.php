@extends('vendor.layouts.dashboard')

@section('title', 'Time Slots')
@section('header', 'Time Slots')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Time Slots for: {{ $service->name }}</h3>
    <a href="{{ route('vendor.timeslots.create', $service->id) }}" class="btn-primary">+ Add Slot</a>
</div>

<div class="mb-4">
    <a href="{{ route('vendor.services') }}" class="text-purple-400 hover:text-purple-300 text-sm">&larr; Back to Services</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Day</th>
                <th>Start</th>
                <th>End</th>
                <th>Capacity</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($slots ?? [] as $slot)
                <tr>
                    <td class="text-sm">{{ $slot->day_of_week !== null ? ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$slot->day_of_week] : 'All Days' }}</td>
                    <td class="text-sm font-mono">{{ $slot->start_time }}</td>
                    <td class="text-sm font-mono">{{ $slot->end_time }}</td>
                    <td class="text-sm">{{ $slot->capacity }}</td>
                    <td class="text-sm font-mono">{{ $slot->price_override ? '₹'.number_format($slot->price_override, 0) : 'Default' }}</td>
                    <td>
                        @if($slot->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-red">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm space-x-2">
                        <a href="{{ route('vendor.timeslots.edit', $slot->id) }}" class="text-purple-400 hover:text-purple-300">Edit</a>
                        <form method="POST" action="{{ route('vendor.timeslots.destroy', $slot->id) }}" data-confirm="Delete this time slot?" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-slate-400 py-8">No time slots yet. <a href="{{ route('vendor.timeslots.create', $service->id) }}" class="text-purple-400">Add one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
