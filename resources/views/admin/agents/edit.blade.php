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
                <input type="text" name="model" value="{{ old('model', $agent->model) }}" class="input-dark">
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
@endsection
