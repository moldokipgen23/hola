@extends('layouts.admin')

@section('title', 'Create Agent')
@section('header', 'Create New AI Agent')

@section('content')
<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ route('admin.agents.store') }}">
        @csrf

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
                    <label class="block text-sm font-medium text-slate-400 mb-1">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="input-dark" placeholder="e.g., Lamka Scout">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Avatar (emoji)</label>
                    <input type="text" name="avatar" value="{{ old('avatar', '🤖') }}"
                        class="input-dark" placeholder="🤖">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Role *</label>
                <input type="text" name="role" value="{{ old('role') }}" required
                    class="input-dark" placeholder="e.g., Business Discovery">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Description</label>
                <textarea name="description" rows="2" class="input-dark"
                    placeholder="What does this agent do?">{{ old('description') }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h3 class="text-lg font-semibold text-white mb-2">AI Configuration</h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Provider *</label>
                    <select name="provider" class="input-dark">
                        <option value="openrouter" {{ old('provider') === 'openrouter' ? 'selected' : '' }}>OpenRouter (Recommended)</option>
                        <option value="deepseek" {{ old('provider') === 'deepseek' ? 'selected' : '' }}>DeepSeek</option>
                        <option value="openai" {{ old('provider') === 'openai' ? 'selected' : '' }}>OpenAI</option>
                        <option value="anthropic" {{ old('provider') === 'anthropic' ? 'selected' : '' }}>Anthropic</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Model *</label>
                    <select name="model" id="model-select" class="input-dark">
                    </select>
                    <p id="model-hint" class="text-slate-500 text-xs mt-1"></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">API Key (leave empty for global key)</label>
                <input type="password" name="api_key" value="{{ old('api_key') }}"
                    class="input-dark" placeholder="sk-or-...">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">System Prompt</label>
                <textarea name="system_prompt" rows="3" class="input-dark"
                    placeholder="Custom instructions for this agent...">{{ old('system_prompt') }}</textarea>
            </div>
        </div>

        <div class="glass-card p-6 rounded-lg space-y-4 mt-4">
            <h3 class="text-lg font-semibold text-white mb-2">Skills</h3>
            <p class="text-sm text-slate-400">Select what this agent can do.</p>

            <div class="grid grid-cols-1 gap-2">
                @php
                    $skills = [
                        'google_places_import' => ['Import businesses from Google Maps', '🏪'],
                        'serpapi_business_search' => ['Search businesses via Google (SerpAPI)', '🌐'],
                        'ai_business_scraper' => ['Discover businesses via AI', '🤖'],
                        'auto_categorize' => ['Auto-match businesses to categories', '📂'],
                        'duplicate_detector' => ['Find and flag duplicate listings', '🔎'],
                        'description_writer' => ['Generate business descriptions', '✍️'],
                        'quality_checker' => ['Rate listing completeness', '✅'],
                        'csv_importer' => ['Bulk import from CSV files', '📄'],
                    ];
                @endphp

                @foreach($skills as $key => [$desc, $icon])
                    <label class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/50 hover:bg-slate-800 cursor-pointer transition-colors">
                        <input type="checkbox" name="skills[]" value="{{ $key }}"
                            {{ in_array($key, old('skills', [])) ? 'checked' : '' }}
                            class="rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500/30">
                        <span class="text-xl">{{ $icon }}</span>
                        <div>
                            <span class="text-sm font-medium text-white">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                            <span class="text-xs text-slate-400 block">{{ $desc }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit" class="btn-primary">Create Agent</button>
            <a href="{{ route('admin.agents') }}" class="btn-ghost">Cancel</a>
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

function populateModels(provider) {
    const select = document.getElementById('model-select');
    const hint = document.getElementById('model-hint');
    const list = models[provider] || models.openrouter;

    select.innerHTML = '';
    list.forEach((m, i) => {
        const opt = document.createElement('option');
        opt.value = m.value;
        opt.textContent = m.label;
        if (i === 0) opt.selected = true;
        select.appendChild(opt);
    });

    hint.textContent = list[0].hint;
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

populateModels('{{ old('provider', 'openrouter') }}');
</script>
@endpush
@endsection
