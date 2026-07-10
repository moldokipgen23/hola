@extends('layouts.admin')

@section('title', 'Autopilot — Hola Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">🤖 Autopilot Dashboard</h1>
            <p class="text-slate-400 text-sm">AI agent works 24/7 — this is what it's doing</p>
        </div>
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('admin.autopilot.toggle') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-all
                    {{ ($agent->status ?? 'paused') === 'active'
                        ? 'bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30'
                        : 'bg-red-500/20 text-red-400 hover:bg-red-500/30' }}">
                    <span class="w-3 h-3 rounded-full {{ ($agent->status ?? 'paused') === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' }}"></span>
                    {{ ($agent->status ?? 'paused') === 'active' ? 'AUTOPILOT ON — Click to turn OFF' : 'AUTOPILOT OFF — Click to turn ON' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="glass-card p-4 rounded-xl text-center">
            <p class="text-3xl font-bold text-white">{{ $totalBusinesses }}</p>
            <p class="text-xs text-slate-400 mt-1">Businesses</p>
        </div>
        <div class="glass-card p-4 rounded-xl text-center">
            <p class="text-3xl font-bold text-yellow-400">{{ $pendingImports }}</p>
            <p class="text-xs text-slate-400 mt-1">Pending Review</p>
        </div>
        <div class="glass-card p-4 rounded-xl text-center">
            <p class="text-3xl font-bold text-blue-400">{{ $totalCategories }}</p>
            <p class="text-xs text-slate-400 mt-1">Categories</p>
        </div>
        <div class="glass-card p-4 rounded-xl text-center">
            <p class="text-3xl font-bold text-emerald-400">{{ $todaysTasks }}</p>
            <p class="text-xs text-slate-400 mt-1">Tasks Today</p>
        </div>
        <div class="glass-card p-4 rounded-xl text-center">
            <p class="text-3xl font-bold text-purple-400">{{ $todaysImports }}</p>
            <p class="text-xs text-slate-400 mt-1">Imported Today</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Next Run --}}
        <div class="glass-card p-4 rounded-xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">⏰</span>
                    <div>
                        <p class="text-white font-semibold">Next Auto-Run</p>
                        <p class="text-slate-400 text-sm">Runs every 4 hours automatically</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-white font-mono text-lg">{{ $nextRun->format('H:i') }}</p>
                    <p class="text-slate-500 text-xs">{{ $nextRun->diffForHumans() }}</p>
                </div>
            </div>
        </div>

        {{-- Last Run --}}
        @if($lastRun)
        <div class="glass-card p-4 rounded-xl">
            <div class="flex items-center gap-3">
                <span class="text-2xl">{{ $lastRun->status === 'completed' ? '✅' : '❌' }}</span>
                <div class="flex-1">
                    <p class="text-white font-semibold">Last Run: {{ str_replace('_', ' ', $lastRun->type) }}</p>
                    <p class="text-slate-400 text-sm">
                        {{ $lastRun->result_count }} results · {{ $lastRun->imported_count }} imported ·
                        @if($lastRun->cost > 0) ${{ number_format($lastRun->cost, 4) }} · @endif
                        {{ $lastRun->duration_ms }}ms
                    </p>
                </div>
                <span class="text-xs text-slate-500">{{ $lastRun->created_at->diffForHumans() }}</span>
            </div>
        </div>
        @endif

    </div>

    {{-- Agent Rules / System Prompt --}}
    <div class="glass-card p-6 rounded-xl mb-6">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xl">📋</span>
            <h3 class="font-semibold text-white">Agent Rules & Prompts</h3>
        </div>
        <p class="text-slate-400 text-sm mb-3">These rules control how the agent behaves — what it searches, how it categorizes, and what it rejects.</p>
        <form method="POST" action="{{ route('admin.autopilot.prompt') }}">
            @csrf
            <textarea name="system_prompt" rows="12" class="input-dark w-full font-mono text-sm">{{ old('system_prompt', $agent->system_prompt ?? '') }}</textarea>
            <div class="flex justify-end mt-3">
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-500/20 text-blue-400 hover:bg-blue-500/30 text-sm font-medium">
                    Save Rules
                </button>
            </div>
        </form>
    </div>

    {{-- Skills --}}
    <div class="glass-card p-6 rounded-xl mb-6">
        <div class="flex items-center gap-2 mb-4">
            <span class="text-xl">🛠️</span>
            <h3 class="font-semibold text-white">Active Skills</h3>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($agent->skills ?? [] as $skill)
                <span class="px-3 py-1.5 rounded-lg bg-slate-800/50 text-sm text-slate-300">
                    {{ str_replace('_', ' ', ucfirst($skill)) }}
                </span>
            @endforeach
        </div>
        <p class="text-slate-500 text-xs mt-3">Skills run automatically. Only Google Places and SerpAPI are used — 100% real data.</p>
    </div>

    {{-- Activity Feed --}}
    <div class="glass-card p-6 rounded-xl">
        <h3 class="font-semibold text-white mb-4">📋 Recent Activity</h3>
        @if($recentTasks->isEmpty())
            <p class="text-slate-500 text-sm">No tasks yet. The agent will start working on its next scheduled run.</p>
        @else
            <div class="space-y-2">
                @foreach($recentTasks as $task)
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/50">
                        <span class="text-lg">
                            @if($task->status === 'completed') ✅
                            @elseif($task->status === 'failed') ❌
                            @elseif($task->status === 'running') ⏳
                            @else ⏸️
                            @endif
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white">{{ str_replace('_', ' ', $task->type) }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $task->result_count }} results · {{ $task->imported_count }} imported
                                @if($task->cost > 0) · ${{ number_format($task->cost, 4) }} @endif
                                @if($task->duration_ms) · {{ $task->duration_ms }}ms @endif
                            </p>
                            @if(!empty($task->search_metadata))
                                <div class="flex gap-2 mt-1 text-[10px]">
                                    @if(($task->search_metadata['new_places'] ?? 0) > 0)
                                        <span class="text-emerald-400">✨ {{ $task->search_metadata['new_places'] }} new</span>
                                    @endif
                                    @if(($task->search_metadata['already_imported'] ?? 0) > 0)
                                        <span class="text-yellow-400">📦 {{ $task->search_metadata['already_imported'] }} already in DB</span>
                                    @endif
                                    @if(($task->search_metadata['disappeared_count'] ?? 0) > 0)
                                        <span class="text-red-400">❌ {{ $task->search_metadata['disappeared_count'] }} disappeared</span>
                                    @endif
                                </div>
                            @endif
                            @if($task->error)
                                <p class="text-xs text-red-400 mt-1">{{ Str::limit($task->error, 100) }}</p>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500 whitespace-nowrap">{{ $task->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
