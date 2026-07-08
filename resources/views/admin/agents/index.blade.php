@extends('layouts.admin')

@section('title', 'AI Agents')
@section('header', 'AI Agents — Digital Workforce')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <p class="text-slate-400">Manage your AI agents. Each agent has unique skills and can perform tasks automatically.</p>
    <a href="{{ route('admin.agents.create') }}" class="btn-primary">+ New Agent</a>
</div>

@if(empty($agents))
    <div class="glass-card p-12 rounded-xl text-center">
        <div class="text-5xl mb-4">🤖</div>
        <h3 class="text-lg font-semibold text-white mb-2">No agents yet</h3>
        <p class="text-slate-400 mb-4">Create your first AI agent to start automating business discovery.</p>
        <a href="{{ route('admin.agents.create') }}" class="btn-primary">Create Agent</a>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($agents as $agent)
            <a href="{{ route('admin.agents.show', $agent['id']) }}" class="glass-card p-6 rounded-xl hover:border-cyan-500/30 transition-all group">
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500/20 to-purple-500/20 flex items-center justify-center text-2xl">
                        {{ $agent['avatar'] ?? '🤖' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-white truncate">{{ $agent['name'] }}</h3>
                        <p class="text-sm text-slate-400 truncate">{{ $agent['role'] }}</p>
                    </div>
                    @if($agent['status'] === 'active')
                        <span class="w-2 h-2 rounded-full bg-emerald-400 mt-2"></span>
                    @else
                        <span class="w-2 h-2 rounded-full bg-slate-500 mt-2"></span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-1 mb-3">
                    @foreach($agent['skills'] ?? [] as $skill)
                        <span class="px-2 py-0.5 text-xs rounded-full bg-slate-700/50 text-slate-300">
                            {{ str_replace('_', ' ', $skill) }}
                        </span>
                    @endforeach
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>{{ $agent['tasks_completed'] ?? 0 }} tasks done</span>
                    <span>{{ ucfirst($agent['provider']) }}</span>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
