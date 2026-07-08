@extends('layouts.admin')

@section('title', 'Edit ' . $agent->name)
@section('header', 'Edit Agent — ' . $agent->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ route('admin.agents.update', $agent->id) }}">
        @csrf @method('PUT')

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="glass-card p-6 rounded-lg space-y-4">
            <h3 class="text-lg font-semibold text-white mb-2">Basic Info</h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $agent->name) }}" required class="input-dark">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Avatar</label>
                    <input type="text" name="avatar" value="{{ old('avatar', $agent->avatar) }}" class="input-dark">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Role</label>
                <input type="text" name="role" value="{{ old('role', $agent->role) }}" required class="input-dark">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="2" class="input-dark">{{ old('description', $agent->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Status</label>
                    <select name="status" class="input-dark">
                        <option value="active" {{ $agent->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="paused" {{ $agent->status === 'paused' ? 'selected' : '' }}>Paused</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Provider</label>
                    <select name="provider" class="input-dark">
                        <option value="openrouter" {{ $agent->provider === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                        <option value="deepseek" {{ $agent->provider === 'deepseek' ? 'selected' : '' }}>DeepSeek</option>
                        <option value="openai" {{ $agent->provider === 'openai' ? 'selected' : '' }}>OpenAI</option>
                        <option value="anthropic" {{ $agent->provider === 'anthropic' ? 'selected' : '' }}>Anthropic</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Model</label>
                <select name="model" id="model-select" class="input-dark">
                </select>
                <p id="model-hint" class="text-slate-500 text-xs mt-1"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">API Key (empty = use global)</label>
                <input type="password" name="api_key" value="" class="input-dark" placeholder="Leave empty to keep current">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">System Prompt</label>
                <textarea name="system_prompt" rows="3" class="input-dark">{{ old('system_prompt', $agent->system_prompt) }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h3 class="text-lg font-semibold text-white mb-2">Skills</h3>
            @php
                $allSkills = [
                    'google_places_import' => 'Google Places Import',
                    'serpapi_business_search' => 'SerpAPI Business Search',
                    'ai_business_scraper' => 'AI Business Scraper',
                    'auto_categorize' => 'Auto Categorize',
                    'duplicate_detector' => 'Duplicate Detector',
                    'description_writer' => 'Description Writer',
                    'quality_checker' => 'Quality Checker',
                    'csv_importer' => 'CSV Importer',
                ];
            @endphp
            <div class="grid grid-cols-2 gap-2">
                @foreach($allSkills as $key => $label)
                    <label class="flex items-center gap-2 p-2 rounded-lg bg-slate-800/50 cursor-pointer">
                        <input type="checkbox" name="skills[]" value="{{ $key }}"
                            {{ in_array($key, old('skills', $agent->skills ?? [])) ? 'checked' : '' }}
                            class="rounded border-slate-600 bg-slate-700 text-cyan-500">
                        <span class="text-sm text-white">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">Update Agent</button>
            <a href="{{ route('admin.agents.show', $agent->id) }}" class="btn-ghost">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
const models = {
    deepseek: [
        { value: 'deepseek-chat', label: 'DeepSeek Chat (V3)', hint: 'Best all-around — fast, cheap, great quality' },
        { value: 'deepseek-reasoner', label: 'DeepSeek Reasoner (R1)', hint: 'Best for complex reasoning — slower, more expensive' },
    ],
    openai: [
        { value: 'gpt-4o', label: 'GPT-4o', hint: 'Best quality — smartest, most reliable' },
        { value: 'gpt-4o-mini', label: 'GPT-4o Mini', hint: 'Cheapest OpenAI — fast, good for simple tasks' },
        { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo', hint: 'Legacy — very cheap but less capable' },
    ],
    openrouter: [
        { value: 'deepseek/deepseek-chat', label: 'DeepSeek V3 (via OpenRouter)', hint: 'Cheapest overall — good quality' },
        { value: 'deepseek/deepseek-r1', label: 'DeepSeek R1 (via OpenRouter)', hint: 'Best reasoning on OpenRouter' },
        { value: 'openai/gpt-4o', label: 'GPT-4o (via OpenRouter)', hint: 'Top quality, moderate cost' },
        { value: 'openai/gpt-4o-mini', label: 'GPT-4o Mini (via OpenRouter)', hint: 'Cheap OpenAI via OpenRouter' },
        { value: 'anthropic/claude-3.5-sonnet', label: 'Claude 3.5 Sonnet (via OpenRouter)', hint: 'Best for writing' },
        { value: 'meta-llama/llama-3.1-70b-instruct', label: 'Llama 3.1 70B', hint: 'Open-source — very cheap' },
    ],
    anthropic: [
        { value: 'claude-3-5-sonnet-20241022', label: 'Claude 3.5 Sonnet', hint: 'Best Anthropic — excellent writing & analysis' },
        { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku', hint: 'Cheapest Anthropic — fast, good for simple tasks' },
    ],
};

const currentModel = '{{ $agent->model }}';

function populateModels(provider) {
    const select = document.getElementById('model-select');
    const hint = document.getElementById('model-hint');
    const list = models[provider] || models.openrouter;

    select.innerHTML = '';
    let selected = false;
    list.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.value;
        opt.textContent = m.label;
        if (m.value === currentModel) {
            opt.selected = true;
            hint.textContent = m.hint;
            selected = true;
        }
        select.appendChild(opt);
    });

    if (!selected) hint.textContent = list[0].hint;
}

document.querySelector('select[name="provider"]').addEventListener('change', function() {
    populateModels(this.value);
});

document.getElementById('model-select').addEventListener('change', function() {
    const provider = document.querySelector('select[name="provider"]').value;
    const list = models[provider] || models.openrouter;
    const found = list.find(m => m.value === this.value);
    document.getElementById('model-hint').textContent = found ? found.hint : '';
});

populateModels('{{ $agent->provider }}');
</script>
@endpush
@endsection
