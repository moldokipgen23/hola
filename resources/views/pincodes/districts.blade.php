@extends('layouts.admin')

@section('title', 'Districts in ' . $state)
@section('header', 'Serviceable Areas')

@section('content')
<div class="mb-4 flex items-center gap-3">
    <a href="{{ route('admin.pincodes') }}" class="text-slate-400 hover:text-white text-sm inline-flex items-center gap-1">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        All States
    </a>
    <span class="text-slate-600">/</span>
    <span class="text-white text-sm">{{ $state }}</span>
</div>

<div class="glass-card p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-white font-semibold">Districts in {{ $state }}</h3>
        <form method="POST" action="{{ route('admin.pincodes.toggle-state') }}" class="inline" onsubmit="return confirm('Toggle all pincodes in {{ $state }}?')">
            @csrf
            <input type="hidden" name="state" value="{{ $state }}">
            <input type="hidden" name="enable" value="{{ $serviceableCount > 0 ? '0' : '1' }}">
            <button type="submit" class="btn-sm {{ $serviceableCount > 0 ? 'bg-green-500/20 text-green-400' : 'bg-slate-700 text-slate-400' }}">
                {{ $serviceableCount > 0 ? 'Disable All' : 'Enable All' }}
            </button>
        </form>
    </div>
    <div class="space-y-2">
        @forelse($districts as $d)
            <div class="flex items-center justify-between bg-white/5 rounded-xl p-4 hover:bg-white/10 transition">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <a href="{{ route('admin.pincodes.localities', [$state, $d['district']]) }}" class="flex-1 min-w-0">
                        <p class="text-white font-medium">{{ $d['district'] }}</p>
                        <p class="text-slate-500 text-xs">{{ number_format($d['total']) }} pincodes · {{ number_format($d['serviceable']) }} serviceable</p>
                    </a>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                    <div class="h-2 w-24 rounded-full bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $d['serviceable_percent'] > 50 ? 'bg-green-500' : ($d['serviceable_percent'] > 0 ? 'bg-yellow-500' : 'bg-slate-600') }}" style="width: {{ $d['serviceable_percent'] }}%"></div>
                    </div>
                    <span class="text-xs text-slate-400 w-12 text-right">{{ $d['serviceable_percent'] }}%</span>
                    <form method="POST" action="{{ route('admin.pincodes.toggle-district') }}" class="inline" onsubmit="return confirm('{{ $d['serviceable'] > 0 ? 'Disable' : 'Enable' }} all pincodes in {{ $d['district'] }}, {{ $state }}?')">
                        @csrf
                        <input type="hidden" name="state" value="{{ $state }}">
                        <input type="hidden" name="district" value="{{ $d['district'] }}">
                        <input type="hidden" name="enable" value="{{ $d['serviceable'] > 0 ? '0' : '1' }}">
                        <button type="submit" class="btn-sm {{ $d['serviceable'] > 0 ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30' : 'bg-slate-700 text-slate-400 hover:bg-slate-600' }}">
                            {{ $d['serviceable'] > 0 ? 'Enabled' : 'Disabled' }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-slate-600 text-sm italic">No districts found in {{ $state }}.</p>
        @endforelse
    </div>
</div>
@endsection