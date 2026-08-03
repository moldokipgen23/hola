@extends('vendor.layouts.dashboard')

@section('title', 'Experiences')
@section('header', 'Experience Management')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="glass-card p-6 rounded-lg">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Control how customers see and interact with your business. Each experience is a different customer journey.</p>
            </div>
            <div class="text-sm text-slate-400 md:text-right">
                <div>{{ $business->category?->name }}</div>
                @if($business->primary_experience)
                    <span class="badge badge-blue">Primary: {{ ucfirst(str_replace('_', ' ', $business->primary_experience)) }}</span>
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('vendor.businesses.experiences.update', $business->id) }}">
        @csrf @method('PUT')

        <div class="glass-card p-5 rounded-lg mb-5">
            <h4 class="text-white font-semibold mb-3">Primary Experience</h4>
            <p class="text-xs text-slate-500 mb-3">This is the main way customers interact with your business in search results.</p>
            <select name="primary_experience" class="input-dark">
                @foreach(['directory','retail','restaurant','appointment','stay','turf','taxi','shared_transport','vehicle_rental','goods_transport','seat_event'] as $exp)
                    <option value="{{ $exp }}" {{ $business->primary_experience === $exp ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $exp)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="glass-card p-5 rounded-lg mb-5">
            <h4 class="text-white font-semibold mb-3">Enabled Experiences</h4>
            <p class="text-xs text-slate-500 mb-3">Toggle which customer journeys are active. Required modules must be enabled in Business Features first.</p>
            <div class="space-y-3">
                @php
                    $allExperiences = [
                        'directory' => ['label' => 'Directory', 'desc' => 'Basic listing with contact info', 'module' => null],
                        'retail' => ['label' => 'Retail / Shopping', 'desc' => 'Product catalog, cart, COD orders', 'module' => 'catalog'],
                        'restaurant' => ['label' => 'Restaurant', 'desc' => 'Menu browsing, food orders, tracking', 'module' => 'catalog'],
                        'appointment' => ['label' => 'Appointments', 'desc' => 'Service booking, staff, date/time selection', 'module' => 'bookings'],
                        'stay' => ['label' => 'Hotel / Stay', 'desc' => 'Room search, booking, check-in/out', 'module' => 'bookings'],
                        'turf' => ['label' => 'Turf / Sports', 'desc' => 'Slot booking for sports venues', 'module' => 'turf'],
                        'taxi' => ['label' => 'Taxi / Ride', 'desc' => 'Route, vehicle, ride request', 'module' => 'transport'],
                        'shared_transport' => ['label' => 'Shared Transport', 'desc' => 'Bus/train seat booking', 'module' => 'transport'],
                        'vehicle_rental' => ['label' => 'Vehicle Rental', 'desc' => 'Rent vehicles by time/distance', 'module' => 'transport'],
                        'goods_transport' => ['label' => 'Goods Transport', 'desc' => 'Ship goods, cargo, logistics', 'module' => 'transport'],
                        'seat_event' => ['label' => 'Seat Events', 'desc' => 'Event seat booking', 'module' => 'bookings'],
                    ];
                    $enabledExperiences = $business->enabled_experiences ?? ['directory'];
                @endphp

                @foreach($allExperiences as $key => $info)
                    @php
                        $globallyEnabled = $globallyEnabledExperiences ?? [];
                        $disabled = ! in_array($key, $globallyEnabled, true);
                        $enabled = ! $disabled && in_array($key, old('enabled_experiences', $enabledExperiences));
                        $moduleReady = !$info['module'] || ($business->enabled_modules[$info['module']] ?? false);
                    @endphp
                    <label class="flex items-start gap-3 p-3 rounded-lg border {{ $enabled ? 'border-purple-500/50 bg-purple-500/5' : 'border-white/5' }} transition-colors {{ $disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}"
                           @if($disabled) title="Unavailable — this experience is switched off in Launch Controls" @endif>
                        <input type="checkbox" name="enabled_experiences[]" value="{{ $key }}" {{ $enabled ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }} class="mt-1">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-white text-sm font-medium">{{ $info['label'] }}</span>
                                @if($disabled)
                                    <span class="badge badge-gray">Off globally</span>
                                @elseif($info['module'])
                                    <span class="text-xs {{ $moduleReady ? 'text-green-400' : 'text-slate-500' }}">
                                        ({{ $moduleReady ? 'Module ready' : 'Needs '.ucfirst($info['module']).' module' }})
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $info['desc'] }}</p>
                            @if($disabled)
                                <p class="text-xs text-slate-500 mt-0.5">Switched off in Launch Controls. Contact the administrator to enable it platform-wide.</p>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="glass-card p-5 rounded-lg flex justify-between items-center">
            <p class="text-xs text-slate-500">Payments remain offline/COD. Customers contact you directly.</p>
            <button class="btn-primary">Save Experiences</button>
        </div>
    </form>

    {{-- Availability per experience --}}
    <div class="glass-card p-5 rounded-lg">
        <h4 class="text-white font-semibold mb-3">Availability Mode per Experience</h4>
        <p class="text-xs text-slate-500 mb-4">Control how customers interact with each enabled experience.</p>
        <div class="space-y-3">
            @php
                $config = $business->experience_config ?? [];
                $readiness = $readiness ?? [];
            @endphp
            @foreach(($business->enabled_experiences ?? ['directory']) as $exp)
                @php
                    $info = $allExperiences[$exp] ?? null;
                    $currentMode = $config[$exp]['availability_mode'] ?? 'live';
                    $expReady = $readiness[$exp]['ready'] ?? false;
                @endphp
                <div class="flex items-center gap-4 p-3 rounded-lg border border-white/5">
                    <div class="flex-1 min-w-0">
                        <span class="text-white text-sm font-medium">{{ $info['label'] ?? ucfirst(str_replace('_', ' ', $exp)) }}</span>
                        @if(!$expReady)
                            <span class="text-xs text-amber-400 ml-2">Not ready</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('vendor.businesses.experiences.availability', [$business->id, $exp]) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <select name="availability_mode" class="input-dark text-sm" style="width: 150px">
                            <option value="live" {{ $currentMode === 'live' ? 'selected' : '' }}>Live</option>
                            <option value="request" {{ $currentMode === 'request' ? 'selected' : '' }}>Request</option>
                            <option value="contact" {{ $currentMode === 'contact' ? 'selected' : '' }}>Contact Only</option>
                        </select>
                        <button type="submit" class="px-3 py-1 text-xs rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20">Save</button>
                    </form>
                </div>
            @endforeach
            @if(empty($business->enabled_experiences))
                <p class="text-slate-500 text-sm text-center py-4">No experiences enabled yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection
