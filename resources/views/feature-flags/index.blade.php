@extends('admin.layouts.app')

@section('title', 'Feature Flags')
@section('header', 'Feature Flags')

@section('content')
<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <p class="text-slate-400 text-sm">Control which features are active, coming soon, or restricted to specific areas.</p>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="btn-primary">Add Feature Flag</button>
    </div>

    @php $groups = $flags->groupBy('group'); @endphp
    @foreach($groups as $group => $groupFlags)
    <div class="mb-8">
        <h3 class="text-white font-semibold text-lg mb-4 capitalize">{{ $group }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($groupFlags as $flag)
            <div class="glass-card p-4 rounded-lg">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="text-white font-medium">{{ $flag->name }}</h4>
                            @if($flag->launch_phase)
                                <span class="badge badge-blue text-xs">{{ $flag->launch_phase }}</span>
                            @endif
                        </div>
                        <p class="text-slate-500 text-xs mt-1">{{ $flag->key }}</p>
                        @if($flag->description)
                            <p class="text-slate-400 text-sm mt-2">{{ $flag->description }}</p>
                        @endif
                        @if($flag->enabled_areas)
                            <p class="text-slate-500 text-xs mt-2">Areas: {{ implode(', ', $flag->enabled_areas) }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('admin.feature-flags.toggle', $flag->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $flag->is_enabled ? 'bg-green-500' : 'bg-white/10' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $flag->is_enabled ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.feature-flags.destroy', $flag->id) }}" onsubmit="return confirm('Delete this feature flag?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    @if($flags->isEmpty())
        <div class="glass-card p-12 rounded-lg text-center">
            <p class="text-slate-400">No feature flags yet. Create one to control feature visibility.</p>
        </div>
    @endif
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center">
    <div class="glass-card p-6 rounded-xl w-full max-w-md mx-4">
        <h3 class="text-white font-semibold text-lg mb-4">Create Feature Flag</h3>
        <form method="POST" action="{{ route('admin.feature-flags.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Key (unique)</label>
                    <input type="text" name="key" required class="input-dark" placeholder="e.g. taxi_pilot_lamka">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Name</label>
                    <input type="text" name="name" required class="input-dark" placeholder="e.g. Taxi Pilot in Lamka">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Description</label>
                    <textarea name="description" rows="2" class="input-dark" placeholder="What does this flag control?"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-slate-400 mb-1">Group</label>
                        <select name="group" class="input-dark">
                            <option value="worlds">Worlds</option>
                            <option value="categories">Categories</option>
                            <option value="capabilities">Capabilities</option>
                            <option value="general" selected>General</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-slate-400 mb-1">Launch Phase</label>
                        <select name="launch_phase" class="input-dark">
                            <option value="">Always</option>
                            <option value="phase1">Phase 1</option>
                            <option value="phase2">Phase 2</option>
                            <option value="phase3">Phase 3</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_enabled" value="1" class="rounded">
                        <span class="text-sm text-slate-300">Enabled</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_visible_to_customers" value="1" class="rounded">
                        <span class="text-sm text-slate-300">Visible to customers</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2 mt-6">
                <button type="submit" class="btn-primary">Create</button>
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
