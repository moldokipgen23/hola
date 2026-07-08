@extends('layouts.admin')

@section('title', 'Review Imports')
@section('header', 'Review Queue — Pending Imports')

@php
    $dupAgents = \App\Models\AiAgent::whereJsonContains('skills', 'duplicate_detector')->get();
    $qualAgents = \App\Models\AiAgent::whereJsonContains('skills', 'quality_checker')->get();
    $descAgents = \App\Models\AiAgent::whereJsonContains('skills', 'description_writer')->get();
    $allBatches = \App\Models\ImportBatch::where('pending', '>', 0)->orderByDesc('created_at')->get();
@endphp

@section('content')
<div class="mb-6 flex items-center justify-between">
    <p class="text-slate-400">{{ $items->total() }} items pending review</p>
    @if($items->count() > 0)
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.import.approve-all') }}">
                @csrf
                <button type="submit" class="btn-primary text-sm"
                    onclick="return confirm('Approve all {{ $items->total() }} pending items?')">
                    Approve All
                </button>
            </form>
        </div>
    @endif
</div>

<!-- Post-Processing Tools -->
<div class="glass-card p-4 rounded-xl mb-4">
    <h4 class="text-sm font-semibold text-white mb-3">Post-Processing Tools — Clean up pending imports before approving</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Duplicate Detector -->
        <div class="p-3 rounded-lg bg-red-500/5 border border-red-500/10">
            <p class="text-xs font-semibold text-red-400 mb-2">🔎 Find & Mark Duplicates</p>
            <p class="text-slate-500 text-xs mb-2">Marks items that already exist in the database as duplicates</p>
            @if($dupAgents->isNotEmpty())
                <form method="POST" action="{{ route('admin.agents.run', $dupAgents->first()->id) }}" class="space-y-2 process-form">
                    @csrf
                    <input type="hidden" name="skill" value="duplicate_detector">
                    <input type="hidden" name="max_results" value="500">
                    <select name="batch_id" class="input-dark text-xs w-full">
                        <option value="">All Batches</option>
                        @foreach($allBatches as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->pending }} pending)</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="agent_id_selector" class="input-dark text-xs flex-1 agent-select">
                            @foreach($dupAgents as $a)
                                <option value="{{ $a->id }}">{{ $a->avatar }} {{ $a->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 whitespace-nowrap">Run</button>
                    </div>
                </form>
            @else
                <p class="text-slate-500 text-xs">Create an agent with 'duplicate_detector' skill first</p>
            @endif
        </div>

        <!-- Quality Checker -->
        <div class="p-3 rounded-lg bg-yellow-500/5 border border-yellow-500/10">
            <p class="text-xs font-semibold text-yellow-400 mb-2">✅ Recalculate Quality Scores</p>
            <p class="text-slate-500 text-xs mb-2">Updates confidence % based on how complete each item's data is</p>
            @if($qualAgents->isNotEmpty())
                <form method="POST" action="{{ route('admin.agents.run', $qualAgents->first()->id) }}" class="space-y-2 process-form">
                    @csrf
                    <input type="hidden" name="skill" value="quality_checker">
                    <input type="hidden" name="max_results" value="500">
                    <select name="batch_id" class="input-dark text-xs w-full">
                        <option value="">All Batches</option>
                        @foreach($allBatches as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->pending }} pending)</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="agent_id_selector" class="input-dark text-xs flex-1 agent-select">
                            @foreach($qualAgents as $a)
                                <option value="{{ $a->id }}">{{ $a->avatar }} {{ $a->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 whitespace-nowrap">Run</button>
                    </div>
                </form>
            @else
                <p class="text-slate-500 text-xs">Create an agent with 'quality_checker' skill first</p>
            @endif
        </div>

        <!-- Description Writer -->
        <div class="p-3 rounded-lg bg-blue-500/5 border border-blue-500/10">
            <p class="text-xs font-semibold text-blue-400 mb-2">✍️ Generate Descriptions (AI)</p>
            <p class="text-slate-500 text-xs mb-2">Uses AI to write descriptions for items missing them</p>
            @if($descAgents->isNotEmpty())
                <form method="POST" action="{{ route('admin.agents.run', $descAgents->first()->id) }}" class="space-y-2 process-form">
                    @csrf
                    <input type="hidden" name="skill" value="description_writer">
                    <input type="hidden" name="max_results" value="20">
                    <select name="batch_id" class="input-dark text-xs w-full">
                        <option value="">All Batches</option>
                        @foreach($allBatches as $b)
                            <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ $b->pending }} pending)</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="agent_id_selector" class="input-dark text-xs flex-1 agent-select">
                            @foreach($descAgents as $a)
                                <option value="{{ $a->id }}">{{ $a->avatar }} {{ $a->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 whitespace-nowrap">Run</button>
                    </div>
                </form>
            @else
                <p class="text-slate-500 text-xs">Create an agent with 'description_writer' skill first</p>
            @endif
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<div id="bulk-actions" class="glass-card p-3 rounded-xl mb-4 flex items-center gap-4" style="display:none">
    <span class="text-white text-sm"><span id="selected-count">0</span> selected</span>
    <button type="button" onclick="bulkApprove()" class="px-3 py-1.5 text-xs rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500/20 transition">Approve Selected</button>
    <button type="button" onclick="bulkReject()" class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">Reject Selected</button>
</div>

<form id="bulk-form" method="POST">
    @csrf
    <input type="hidden" name="ids" id="bulk-ids-input">
</form>

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
                    <div class="pt-1">
                        <input type="checkbox" value="{{ $item->id }}" class="row-checkbox" onchange="updateBulk()">
                    </div>

                    <div class="flex flex-col items-center gap-1">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold
                            {{ ($item->confidence ?? 0) >= 0.7 ? 'bg-emerald-500/20 text-emerald-400' :
                               (($item->confidence ?? 0) >= 0.4 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') }}">
                            {{ round(($item->confidence ?? 0) * 100) }}%
                        </div>
                    </div>

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
                            @if(!empty($item->data['rating']))
                                <div>⭐ {{ $item->data['rating'] }} ({{ $item->data['total_ratings'] ?? 0 }})</div>
                            @endif
                        </div>

                        @if(!empty($item->data['description']))
                            <p class="text-xs text-slate-500 mt-1">{{ Str::limit($item->data['description'], 150) }}</p>
                        @endif
                    </div>

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

<script>
document.querySelectorAll('.agent-select').forEach(select => {
    const form = select.closest('form');
    const updateAction = () => {
        form.action = '/admin/agents/' + select.value + '/run';
    };
    select.addEventListener('change', updateAction);
    updateAction();
});

function updateBulk() {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    document.getElementById('selected-count').textContent = checked.length;
    document.getElementById('bulk-actions').style.display = checked.length > 0 ? 'flex' : 'none';
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
}

function bulkApprove() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Approve ' + ids.length + ' items?')) return;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').action = '{{ route("admin.import.bulk-approve") }}';
    document.getElementById('bulk-form').submit();
}

function bulkReject() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Reject ' + ids.length + ' items?')) return;
    document.getElementById('bulk-ids-input').value = JSON.stringify(ids);
    document.getElementById('bulk-form').action = '{{ route("admin.import.bulk-reject") }}';
    document.getElementById('bulk-form').submit();
}
</script>
@endsection
