@extends('layouts.admin')

@section('title', 'Business Modules')
@section('header', 'Business Modules')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="glass-card p-6 rounded-lg">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <a href="{{ route('admin.businesses.show', $business->id) }}" class="text-sm text-slate-400 hover:text-white">← Back to business</a>
                <h2 class="text-xl font-semibold text-white mt-3">{{ $business->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Turn on only the ways this business serves customers. The directory listing always remains available.</p>
            </div>
            <div class="text-sm text-slate-400 md:text-right">{{ $business->category?->name ?? 'Uncategorised' }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.businesses.modules.update', $business->id) }}">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($definitions as $key => $definition)
                @php
                    $enabled = old('modules') !== null ? in_array($key, old('modules', []), true) : ($modules[$key] ?? false);
                    $state = $readiness[$key] ?? [];
                @endphp
                <label class="glass-card p-5 rounded-lg cursor-pointer border {{ $enabled ? 'border-purple-500/50' : 'border-white/5' }} hover:border-purple-500/40 transition-colors">
                    <div class="flex items-start gap-3">
                        <input type="checkbox" name="modules[]" value="{{ $key }}" {{ $enabled ? 'checked' : '' }} class="mt-1">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-white">{{ $definition['label'] }}</h3>
                                @if(in_array($key, $recommended, true))<span class="badge badge-blue">Recommended</span>@endif
                                @if(($state['enabled'] ?? false) && !($state['ready'] ?? true))<span class="badge badge-yellow">Setup needed</span>@endif
                            </div>
                            <p class="text-sm text-slate-400 mt-1">{{ $definition['description'] }}</p>
                            @if($definition['depends_on'])<p class="text-xs text-slate-500 mt-2">Also enables: {{ collect($definition['depends_on'])->map(fn ($dependency) => $definitions[$dependency]['label'])->join(', ') }}</p>@endif
                            @if($state['next_step'] ?? null)<p class="text-xs text-amber-400 mt-2">Next: {{ $state['next_step'] }}</p>@endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
        <div class="glass-card p-5 rounded-lg mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-slate-500">Payments are offline/COD. Customers contact and pay the business directly.</p>
            <div class="flex gap-2">
                <a href="{{ route('admin.businesses.show', $business->id) }}" class="btn-ghost">Cancel</a>
                <button class="btn-primary">Save modules</button>
            </div>
        </div>
    </form>
</div>
@endsection
