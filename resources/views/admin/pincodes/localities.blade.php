@extends('layouts.admin')

@section('title', $district . ' — Pincodes')
@section('header', 'Serviceable Areas')

@section('content')
<div class="mb-4 flex items-center gap-3">
    <a href="{{ route('admin.pincodes') }}" class="text-slate-400 hover:text-white text-sm inline-flex items-center gap-1">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        States
    </a>
    <span class="text-slate-600">/</span>
    <a href="{{ route('admin.pincodes.districts', $state) }}" class="text-slate-400 hover:text-white text-sm">{{ $state }}</a>
    <span class="text-slate-600">/</span>
    <span class="text-white text-sm">{{ $district }}</span>
</div>

<div class="glass-card p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-white font-semibold">Localities in {{ $district }}, {{ $state }}</h3>
        <form method="POST" action="{{ route('admin.pincodes.toggle-district') }}" class="inline" onsubmit="return confirm('Toggle all pincodes in {{ $district }}?')">
            @csrf
            <input type="hidden" name="state" value="{{ $state }}">
            <input type="hidden" name="district" value="{{ $district }}">
            <input type="hidden" name="enable" value="{{ $serviceableCount > 0 ? '0' : '1' }}">
            <button type="submit" class="btn-sm {{ $serviceableCount > 0 ? 'bg-green-500/20 text-green-400' : 'bg-slate-700 text-slate-400' }}">
                {{ $serviceableCount > 0 ? 'Disable All' : 'Enable All' }}
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-slate-500 border-b border-white/5">
                    <th class="text-left py-3 px-2">Pincode</th>
                    <th class="text-left py-3 px-2">Locality</th>
                    <th class="text-left py-3 px-2 hidden md:table-cell">District</th>
                    <th class="text-left py-3 px-2 hidden md:table-cell">State</th>
                    <th class="text-center py-3 px-2">Serviceable</th>
                    <th class="text-center py-3 px-2">Toggle</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pincodes as $p)
                    <tr class="border-b border-white/5 hover:bg-white/5 transition">
                        <td class="py-3 px-2 text-white font-mono">{{ $p->pincode }}</td>
                        <td class="py-3 px-2 text-slate-300">{{ $p->locality ?? '-' }}</td>
                        <td class="py-3 px-2 text-slate-400 hidden md:table-cell">{{ $p->district }}</td>
                        <td class="py-3 px-2 text-slate-400 hidden md:table-cell">{{ $p->state }}</td>
                        <td class="py-3 px-2 text-center">
                            @if($p->serviceable)
                                <span class="text-green-400 text-xs font-semibold">ACTIVE</span>
                            @else
                                <span class="text-slate-600 text-xs">INACTIVE</span>
                            @endif
                        </td>
                        <td class="py-3 px-2 text-center">
                            <form method="POST" action="{{ route('admin.pincodes.toggle-pincode') }}" class="inline">
                                @csrf
                                <input type="hidden" name="id" value="{{ $p->id }}">
                                <input type="hidden" name="state" value="{{ $state }}">
                                <input type="hidden" name="district" value="{{ $district }}">
                                <button type="submit" class="btn-sm {{ $p->serviceable ? 'bg-green-500/20 text-green-400' : 'bg-slate-700 text-slate-400' }}">
                                    {{ $p->serviceable ? 'ON' : 'OFF' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-600 italic">No pincodes found in {{ $district }}, {{ $state }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pincodes->hasPages())
        <div class="mt-4">{{ $pincodes->links() }}</div>
    @endif
</div>
@endsection