@extends('layouts.admin')

@section('title', 'Serviceable Pincodes')
@section('header', 'Serviceable Areas')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <p class="text-slate-500 text-xs">Manage which states, districts, and pincodes are serviceable. Toggle an entire state or district on/off.</p>
</div>

<div class="glass-card p-6">
    <h3 class="text-white font-semibold mb-4">States ({{ $states->count() }})</h3>
    <div class="space-y-2">
        @foreach($states as $s)
            <div class="flex items-center justify-between bg-white/5 rounded-xl p-4 hover:bg-white/10 transition {{ $s['pinned'] ? 'ring-1 ring-blue-500/30 bg-blue-500/5' : '' }}">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    @if($s['pinned'])
                        <svg fill="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-blue-400 flex-shrink-0"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                    @endif
                    <a href="{{ route('admin.pincodes.districts', $s['state']) }}" class="flex-1 min-w-0">
                        <p class="text-white font-medium">{{ $s['state'] }}</p>
                        <p class="text-slate-500 text-xs">{{ number_format($s['total']) }} pincodes · {{ number_format($s['serviceable']) }} serviceable</p>
                    </a>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                    <div class="h-2 w-24 rounded-full bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $s['serviceable_percent'] > 50 ? 'bg-green-500' : ($s['serviceable_percent'] > 0 ? 'bg-yellow-500' : 'bg-slate-600') }}" style="width: {{ $s['serviceable_percent'] }}%"></div>
                    </div>
                    <span class="text-xs text-slate-400 w-12 text-right">{{ $s['serviceable_percent'] }}%</span>
                    <form method="POST" action="{{ route('admin.pincodes.toggle-pin') }}" class="inline">
                        @csrf
                        <input type="hidden" name="state" value="{{ $s['state'] }}">
                        <button type="submit" class="btn-sm {{ $s['pinned'] ? 'bg-blue-500/20 text-blue-400' : 'bg-slate-700 text-slate-500' }}" title="{{ $s['pinned'] ? 'Unpin' : 'Pin to top' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.pincodes.toggle-state') }}" class="inline" onsubmit="return confirm('{{ $s['serviceable'] > 0 ? 'Disable' : 'Enable' }} all pincodes in {{ $s['state'] }}?')">
                        @csrf
                        <input type="hidden" name="state" value="{{ $s['state'] }}">
                        <input type="hidden" name="enable" value="{{ $s['serviceable'] > 0 ? '0' : '1' }}">
                        <button type="submit" class="btn-sm {{ $s['serviceable'] > 0 ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30' : 'bg-slate-700 text-slate-400 hover:bg-slate-600' }}">
                            {{ $s['serviceable'] > 0 ? 'Enabled' : 'Disabled' }}
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection