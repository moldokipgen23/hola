@extends('admin.layouts.app')

@section('title', 'Search History — Hola Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Search History</h1>
            <p class="text-slate-400 text-sm">Memory of all past searches across agents</p>
        </div>
    </div>

    @if($history->isEmpty())
        <div class="text-center py-12">
            <div class="text-4xl mb-3">🔍</div>
            <p class="text-slate-400">No searches recorded yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($history as $record)
                <div class="glass-card p-4 rounded-xl">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center text-lg">
                            {{ $record->agent->avatar ?? '🤖' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h4 class="font-semibold text-white">{{ $record->query }}</h4>
                                <span class="px-2 py-0.5 text-xs rounded-full bg-blue-500/20 text-blue-400">
                                    {{ $record->source }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    #{{ $record->agent->name }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                                <div class="text-slate-400">
                                    🔍 <span class="text-white font-medium">{{ $record->total_found }}</span> found
                                </div>
                                <div class="text-emerald-400">
                                    ✨ <span class="text-white font-medium">{{ $record->new_places }}</span> new
                                </div>
                                <div class="text-yellow-400">
                                    📦 <span class="text-white font-medium">{{ $record->already_imported }}</span> already imported
                                </div>
                                <div class="text-red-400">
                                    ⚠️ <span class="text-white font-medium">{{ $record->duplicates }}</span> duplicates
                                </div>
                                <div class="text-slate-500">
                                    📍 {{ $record->area ?? 'Global' }}
                                </div>
                            </div>

                            @if(!empty($record->place_ids) && count($record->place_ids) > 0)
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach(array_slice($record->place_ids, 0, 5) as $pid)
                                        <span class="px-1.5 py-0.5 text-[10px] rounded bg-slate-800/50 text-slate-500 font-mono">
                                            {{ Str::limit($pid, 20, '') }}
                                        </span>
                                    @endforeach
                                    @if(count($record->place_ids) > 5)
                                        <span class="text-[10px] text-slate-600">+{{ count($record->place_ids) - 5 }} more</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="text-xs text-slate-500 whitespace-nowrap">
                            {{ $record->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $history->links() }}
        </div>
    @endif
</div>
@endsection
