@extends('vendor.layouts.dashboard')

@section('title', 'Appointment Calendar')
@section('header', 'Appointment Calendar Management')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="glass-card p-6 rounded-lg">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Manage appointment services, time slots, and availability.</p>
            </div>
            <a href="{{ route('vendor.businesses.modules', $business->id) }}" class="btn-ghost">Business Features</a>
        </div>
    </div>

    @forelse($services as $service)
    <div class="glass-card p-5 rounded-lg">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-white font-semibold">{{ $service->name }}</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Duration: {{ $service->duration }} min · Price: ₹{{ $service->price }} per {{ $service->price_unit }}
                </p>
            </div>
            <form method="POST" action="{{ route('vendor.businesses.appointments.update', [$business->id, $service->id]) }}">
                @csrf @method('PUT')
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" {{ $service->is_active ? 'checked' : '' }} onchange="this.form.submit()">
                    <span class="{{ $service->is_active ? 'text-green-400' : 'text-slate-500' }}">{{ $service->is_active ? 'Active' : 'Inactive' }}</span>
                </label>
            </form>
        </div>

        @if($service->timeSlots->count() > 0)
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Capacity</th>
                        <th>Price Override</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($service->timeSlots as $slot)
                    <tr>
                        <td class="text-sm">{{ $slot->day_of_week !== null ? ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$slot->day_of_week] : 'Any' }}</td>
                        <td class="text-sm">{{ $slot->start_time }}</td>
                        <td class="text-sm">{{ $slot->end_time }}</td>
                        <td class="text-sm">{{ $slot->capacity }}</td>
                        <td class="text-sm">{{ $slot->price_override ? '₹'.$slot->price_override : '—' }}</td>
                        <td>
                            <span class="badge {{ $slot->is_active ? 'badge-green' : 'badge-red' }}">{{ $slot->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('vendor.services.slots.update', [$business->id, $service->id, $slot->id]) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $slot->is_active ? '0' : '1' }}">
                                <input type="hidden" name="start_time" value="{{ $slot->start_time }}">
                                <input type="hidden" name="end_time" value="{{ $slot->end_time }}">
                                <input type="hidden" name="capacity" value="{{ $slot->capacity }}">
                                <button type="submit" class="text-xs text-sky-400">{{ $slot->is_active ? 'Disable' : 'Enable' }}</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-slate-500 text-sm text-center py-4">No time slots configured. Add slots via Bookable Items.</p>
        @endif
    </div>
    @empty
    <div class="glass-card p-10 rounded-lg text-center">
        <p class="text-slate-400 mb-3">No appointment services found.</p>
        <a href="{{ route('vendor.services.create', $business->id) }}" class="btn-primary">Create an Appointment Service</a>
    </div>
    @endforelse
</div>
@endsection
