@extends('vendor.layouts.dashboard')
@section('title', 'Departures / Schedules')
@section('header', 'Departures / Schedules')
@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">Departures & Schedules</h3>
        <p class="text-slate-500 text-sm">Add fixed route departures so customers can search a route + date and pick seats.</p>
    </div>
    <a href="{{ route('vendor.schedules.create', $business->id) }}" class="btn-primary">+ Add Departure</a>
</div>

<div class="space-y-3">
@forelse($schedules as $schedule)
<div class="glass-card p-5 rounded-xl">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <div class="flex gap-2 items-center flex-wrap">
                <span class="text-white font-semibold">{{ $schedule->origin }} → {{ $schedule->destination }}</span>
                <span class="badge {{ $schedule->status === 'cancelled' ? 'badge-red' : 'badge-green' }}">{{ ucfirst($schedule->status) }}</span>
                <span class="badge badge-blue">{{ $schedule->departure_date->format('M d, Y') }} · {{ $schedule->departure_time }}</span>
            </div>
            <p class="text-slate-400 text-sm mt-2">
                {{ $schedule->vehicle->name ?? 'Vehicle' }} ·
                <span class="{{ $schedule->bookings_count > 0 ? 'text-amber-400' : 'text-slate-400' }}">
                    {{ $schedule->bookings_count }} seat booking(s)
                </span>
            </p>
            @if($schedule->notes)
                <p class="text-slate-500 text-xs mt-1">{{ $schedule->notes }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-white font-bold text-lg">₹{{ number_format($schedule->price, 2) }}</p>
            <p class="text-xs text-slate-500">{{ $schedule->seats_capacity }} seats</p>
        </div>
    </div>
    <div class="flex gap-2 mt-4">
        <a href="{{ route('vendor.schedule-bookings', ['businessId' => $business->id, 'date' => $schedule->departure_date->toDateString()]) }}" class="px-3 py-2 rounded-lg bg-sky-500/10 text-sky-400 text-sm">View bookings</a>
        @if($schedule->bookings_count === 0)
        <a href="{{ route('vendor.schedules.edit', [$business->id, $schedule->id]) }}" class="px-3 py-2 rounded-lg bg-amber-500/10 text-amber-400 text-sm">Edit</a>
        <form method="POST" action="{{ route('vendor.schedules.destroy', [$business->id, $schedule->id]) }}" data-confirm="Delete this departure?" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="px-3 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm">Delete</button>
        </form>
        @endif
    </div>
</div>
@empty
<div class="glass-card p-10 rounded-xl text-center text-slate-500">No departures yet. Add one so customers can book seats online.</div>
@endforelse
</div>

<div class="mt-6">{{ $schedules->links() }}</div>
@endsection
