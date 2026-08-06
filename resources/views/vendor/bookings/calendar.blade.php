@extends('vendor.layouts.dashboard')

@section('title', 'Availability Calendar')
@section('header', 'Availability Calendar')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div>
        <h3 class="text-white font-semibold text-lg">{{ $month->format('F Y') }}</h3>
        <p class="text-slate-500 text-sm">Day-by-day bookings for appointments, rooms and events.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('vendor.calendar', [$business->id, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn-ghost">← Previous</a>
        <a href="{{ route('vendor.calendar', [$business->id, 'month' => now()->format('Y-m')]) }}" class="btn-ghost">Today</a>
        <a href="{{ route('vendor.calendar', [$business->id, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn-ghost">Next →</a>
    </div>
</div>

<div class="glass-card rounded-xl overflow-hidden">
    <div class="grid grid-cols-7 border-b border-white/5">
        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
            <div class="px-3 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ $day }}</div>
        @endforeach
    </div>
    @foreach($weeks as $week)
        <div class="grid grid-cols-7 border-b border-white/5 last:border-b-0">
            @foreach($week as $cell)
                @php
                    $inMonth = $cell['date']->month === $month->month;
                    $isToday = $cell['date']->isToday();
                    $count = $cell['bookings']->count();
                @endphp
                <div class="p-2 min-h-[96px] border-r border-white/5 last:border-r-0 {{ $inMonth ? '' : 'bg-white/[0.015]' }}">
                    <span class="inline-flex items-center justify-center w-7 h-7 text-xs font-semibold rounded-full {{ $isToday ? 'bg-purple-500 text-white' : ($inMonth ? 'text-slate-300' : 'text-slate-600') }}">{{ $cell['date']->format('j') }}</span>
                    <div class="mt-1 space-y-1">
                        @foreach($cell['bookings']->take(3) as $booking)
                            @php
                                $color = [
                                    'pending' => 'bg-yellow-500/10 text-yellow-400',
                                    'confirmed' => 'bg-sky-500/10 text-sky-400',
                                    'confirmed_at' => 'bg-sky-500/10 text-sky-400',
                                    'completed' => 'bg-green-500/10 text-green-400',
                                    'cancelled' => 'bg-red-500/10 text-red-400',
                                    'rejected' => 'bg-red-500/10 text-red-400',
                                    'no_show' => 'bg-orange-500/10 text-orange-400',
                                ][$booking->status] ?? 'bg-white/5 text-slate-400';
                            @endphp
                            <div class="px-1.5 py-0.5 rounded text-[11px] leading-tight truncate {{ $color }}" title="{{ $booking->customer_name }} · #{{ $booking->id }}">
                                {{ $booking->start_time ? \Carbon\Carbon::parse($booking->start_time)->format('g:i') : '' }} {{ $booking->customer_name }}
                            </div>
                        @endforeach
                        @if($count > 3)
                            <div class="text-[11px] text-slate-500 px-1">+{{ $count - 3 }} more</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>

<div class="mt-6 space-y-2">
    <h4 class="text-white font-semibold">Legend</h4>
    <div class="flex flex-wrap gap-3 text-xs">
        <span class="badge bg-yellow-500/10 text-yellow-400">Pending</span>
        <span class="badge bg-sky-500/10 text-sky-400">Confirmed</span>
        <span class="badge bg-green-500/10 text-green-400">Completed</span>
        <span class="badge bg-red-500/10 text-red-400">Cancelled / Rejected</span>
        <span class="badge bg-orange-500/10 text-orange-400">No Show</span>
    </div>
</div>
@endsection