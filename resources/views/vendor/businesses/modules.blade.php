@extends('vendor.layouts.dashboard')

@section('title', 'Business Features')
@section('header', 'Business Features')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="glass-card p-6 rounded-lg">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">
                    Every business is always visible in Explore. Enable only the extra ways customers can interact with you.
                </p>
            </div>
            <div class="text-sm text-slate-400 md:text-right">
                <div>{{ $business->category?->name }}</div>
                @if($business->subcategory)
                    <div class="text-slate-500">{{ $business->subcategory->name }}</div>
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('vendor.businesses.modules.update', $business->id) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($definitions as $key => $definition)
                @php
                    $globallyEnabled = $globallyEnabledModules ?? [];
                    $disabled = ! in_array($key, $globallyEnabled, true);
                    $enabled = ! $disabled && (old('modules') !== null
                        ? in_array($key, old('modules', []), true)
                        : ($modules[$key] ?? false));
                    $state = $readiness[$key];
                    $isRecommended = in_array($key, $recommended, true);
                @endphp
                <label class="glass-card p-5 rounded-lg cursor-pointer border {{ $enabled ? 'border-purple-500/50' : 'border-white/5' }} hover:border-purple-500/40 transition-colors {{ $disabled ? 'opacity-60' : '' }}"
                       @if($disabled) title="Unavailable — this feature is switched off in Launch Controls" @endif>
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="modules[]" value="{{ $key }}" {{ $enabled ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }} class="mt-1">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-white">{{ $definition['label'] }}</h3>
                                @if($isRecommended)
                                    <span class="badge badge-blue">Recommended</span>
                                @endif
                                @if($disabled)
                                    <span class="badge badge-gray">Off globally</span>
                                @elseif(($state['enabled'] ?? false) && !($state['ready'] ?? true))
                                    <span class="badge badge-yellow">Setup needed</span>
                                @elseif($state['enabled'] ?? false)
                                    <span class="badge badge-green">Ready</span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-400 mt-1">{{ $definition['description'] }}</p>
                            @if($disabled)
                                <p class="text-xs text-slate-500 mt-2">Switched off in Launch Controls. Contact the administrator to enable it platform-wide.</p>
                            @else
                                @if($definition['depends_on'])
                                    <p class="text-xs text-slate-500 mt-2">
                                        Automatically enables: {{ collect($definition['depends_on'])->map(fn ($dependency) => $definitions[$dependency]['label'])->join(', ') }}
                                    </p>
                                @endif
                                @if($state['next_step'] ?? null)
                                    <p class="text-xs text-amber-400 mt-2">Next: {{ $state['next_step'] }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="glass-card p-5 rounded-lg mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500">
                Payments remain offline/COD. Customers contact and transact directly with your business.
            </p>
            <div class="flex gap-2">
                <a href="{{ route('vendor.businesses') }}" class="btn-ghost">Cancel</a>
                <button class="btn-primary">Save features</button>
            </div>
        </div>
    </form>
</div>
@endsection
