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
                    <input type="text" name="model" value="{{ old('model', 'deepseek/deepseek-chat') }}"
                        class="input-dark" placeholder="deepseek/deepseek-chat">
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
                        'ai_business_scraper' => ['Discover businesses via AI web search', '🔍'],
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
@endsection
