@extends('layouts.admin')

@section('title', $agent['name'])
@section('header', $agent['name'] . ' — ' . ($agent['role'] ?? ''))

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Agent Info -->
    <div class="lg:col-span-1 space-y-4">
        <div class="glass-card p-6 rounded-xl">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-cyan-500/20 to-purple-500/20 flex items-center justify-center text-3xl">
                    {{ $agent['avatar'] ?? '🤖' }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">{{ $agent['name'] }}</h2>
                    <p class="text-slate-400">{{ $agent['role'] }}</p>
                </div>
            </div>

            <div class="space-y-2 text-sm mb-4">
                <div class="flex justify-between">
                    <span class="text-slate-400">Status</span>
                    <span class="{{ $agent['status'] === 'active' ? 'text-emerald-400' : 'text-slate-500' }}">
                        {{ ucfirst($agent['status']) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Provider</span>
                    <span class="text-white">{{ ucfirst($agent['provider']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Model</span>
                    <span class="text-white font-mono text-xs">{{ $agent['model'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Tasks Done</span>
                    <span class="text-white">{{ $agent['tasks_completed'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Tasks Failed</span>
                    <span class="text-white">{{ $agent['tasks_failed'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Total Cost</span>
                    <span class="text-white">${{ number_format($agent['total_cost'] ?? 0, 4) }}</span>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.agents.edit', $agent['id']) }}" class="btn-ghost text-sm flex-1 text-center">Edit</a>
                <form method="POST" action="{{ route('admin.agents.destroy', $agent['id']) }}"
                    onsubmit="return confirm('Delete this agent?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-sm text-red-400 hover:text-red-300">Delete</button>
                </form>
            </div>
        </div>

        <!-- Skills -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="font-semibold text-white mb-3">Skills</h3>
            <div class="space-y-2">
                @foreach($agent['skills'] ?? [] as $skill)
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/50">
                        <span class="text-sm text-white">{{ str_replace('_', ' ', $skill) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Run Agent + Task History -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Run Task -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="font-semibold text-white mb-3">Run Task</h3>
            <form method="POST" action="{{ route('admin.agents.run', $agent['id']) }}">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-slate-400 mb-1">Select Skill</label>
                        <select name="skill" class="input-dark" required>
                            <option value="">Choose a skill...</option>
                            @foreach($agent['skills'] ?? [] as $skill)
                                <option value="{{ $skill }}">{{ str_replace('_', ' ', $skill) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Dynamic fields based on skill -->
                    <div id="skill-fields">
                        <div class="field-group" data-skill="google_places_import">
                            <label class="block text-sm text-slate-400 mb-1">Search Query</label>
                            <input type="text" name="query" class="input-dark" placeholder="e.g., restaurants, schools">
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">City / Area</label>
                                    <input type="text" name="area" class="input-dark" placeholder="e.g., Delhi, Lamka">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Zipcode</label>
                                    <input type="text" name="zipcode" class="input-dark" placeholder="e.g., 110014">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Results</label>
                                <input type="number" name="max_results" class="input-dark" value="20" min="1" max="60">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="ai_business_scraper">
                            <label class="block text-sm text-slate-400 mb-1">Area / Zipcode</label>
                            <input type="text" name="area" class="input-dark" value="Lamka, Churachandpur" placeholder="e.g., 795128 or Lamka, Churachandpur">
                            <label class="block text-sm text-slate-400 mb-1 mt-2">Category</label>
                            <input type="text" name="category" class="input-dark" placeholder="e.g., restaurants, all businesses">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Results</label>
                                <input type="number" name="max_results" class="input-dark" value="30" min="1" max="50">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="serpapi_business_search">
                            <label class="block text-sm text-slate-400 mb-1">Search Query</label>
                            <input type="text" name="query" class="input-dark" placeholder="e.g., restaurants, hotels, shops">
                            <label class="block text-sm text-slate-400 mb-1 mt-2">Area / Zipcode</label>
                            <input type="text" name="area" class="input-dark" value="Lamka, Churachandpur" placeholder="e.g., 795128 or Lamka, Churachandpur">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Results</label>
                                <input type="number" name="max_results" class="input-dark" value="20" min="1" max="50">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="auto_categorize">
                            <label class="block text-sm text-slate-400 mb-1">Batch ID (optional)</label>
                            <input type="number" name="batch_id" class="input-dark" placeholder="Leave empty for all pending">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Items</label>
                                <input type="number" name="max_results" class="input-dark" value="30" min="1" max="100">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="duplicate_detector">
                            <label class="block text-sm text-slate-400 mb-1">Batch ID (optional)</label>
                            <input type="number" name="batch_id" class="input-dark" placeholder="Leave empty for all pending">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Items</label>
                                <input type="number" name="max_results" class="input-dark" value="200" min="1" max="500">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="description_writer">
                            <label class="block text-sm text-slate-400 mb-1">Batch ID (optional)</label>
                            <input type="number" name="batch_id" class="input-dark" placeholder="Leave empty for all pending">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Items</label>
                                <input type="number" name="max_results" class="input-dark" value="10" min="1" max="50">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="quality_checker">
                            <label class="block text-sm text-slate-400 mb-1">Batch ID (optional)</label>
                            <input type="number" name="batch_id" class="input-dark" placeholder="Leave empty for all pending">
                            <div class="mt-2">
                                <label class="block text-xs text-slate-500 mb-1">Max Items</label>
                                <input type="number" name="max_results" class="input-dark" value="200" min="1" max="500">
                            </div>
                        </div>

                        <div class="field-group hidden" data-skill="csv_importer">
                            <label class="block text-sm text-slate-400 mb-1">File Path (server-side)</label>
                            <input type="text" name="file_path" class="input-dark" placeholder="/path/to/file.csv">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary w-full">Run Task</button>
                </div>
            </form>
        </div>

        <!-- Task History -->
        <div class="glass-card p-6 rounded-xl">
            <h3 class="font-semibold text-white mb-3">Recent Tasks</h3>
            @if(empty($recentTasks))
                <p class="text-slate-500 text-sm">No tasks run yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($recentTasks as $task)
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/50">
                            <span class="text-lg">
                                @if($task['status'] === 'completed') ✅
                                @elseif($task['status'] === 'failed') ❌
                                @elseif($task['status'] === 'running') ⏳
                                @else ⏸️
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white">{{ str_replace('_', ' ', $task['type']) }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $task['result_count'] }} results, {{ $task['imported_count'] }} imported
                                    @if($task['cost'] > 0) · ${{ number_format($task['cost'], 4) }} @endif
                                </p>
                            </div>
                            <span class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($task['created_at'])->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script>
document.querySelector('select[name="skill"]').addEventListener('change', function() {
    document.querySelectorAll('.field-group').forEach(el => el.classList.add('hidden'));
    const group = document.querySelector(`[data-skill="${this.value}"]`);
    if (group) group.classList.remove('hidden');
});
</script>
@endsection
