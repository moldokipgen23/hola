@extends('layouts.admin')

@section('title', 'Review Imports')
@section('header', 'Review Queue — Pending Imports')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <p class="text-slate-400">{{ $items->total() }} items pending review</p>
    @if($items->count() > 0)
        <form method="POST" action="{{ route('admin.import.approve-all') }}">
            @csrf
            <button type="submit" class="btn-primary text-sm"
                onclick="return confirm('Approve all {{ $items->total() }} pending items?')">
                Approve All
            </button>
        </form>
    @endif
</div>

@if($items->isEmpty())
    <div class="glass-card p-12 rounded-xl text-center">
        <div class="text-5xl mb-4">✅</div>
        <h3 class="text-lg font-semibold text-white mb-2">All caught up!</h3>
        <p class="text-slate-400">No items pending review.</p>
    </div>
@else
    <div class="space-y-3">
        @foreach($items as $item)
            <div class="glass-card p-4 rounded-xl" id="item-{{ $item->id }}">
                <div class="flex items-start gap-4">
                    <!-- Confidence Bar -->
                    <div class="flex flex-col items-center gap-1">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold
                            {{ ($item->confidence ?? 0) >= 0.7 ? 'bg-emerald-500/20 text-emerald-400' :
                               (($item->confidence ?? 0) >= 0.4 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') }}">
                            {{ round(($item->confidence ?? 0) * 100) }}%
                        </div>
                    </div>

                    <!-- Business Data -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-semibold text-white">{{ $item->data['name'] ?? 'Unknown' }}</h4>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-slate-700/50 text-slate-400">
                                {{ $item->batch->source ?? 'unknown' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-slate-400">
                            @if(!empty($item->data['address']))
                                <div>📍 {{ $item->data['address'] }}</div>
                            @endif
                            @if(!empty($item->data['phone']))
                                <div>📞 {{ $item->data['phone'] }}</div>
                            @endif
                            @if(!empty($item->data['category']))
                                <div>📂 {{ $item->data['category'] }}</div>
                            @endif
                            @if(!empty($item->data['website']))
                                <div>🌐 {{ $item->data['website'] }}</div>
                            @endif
                        </div>

                        @if(!empty($item->data['description']))
                            <p class="text-xs text-slate-500 mt-1">{{ Str::limit($item->data['description'], 150) }}</p>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.import.approve', $item->id) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-400 text-sm hover:bg-emerald-500/30">
                                Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.import.reject', $item->id) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-500/20 text-red-400 text-sm hover:bg-red-500/30">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $items->withQueryString()->links() }}
    </div>
@endif
@endsection
