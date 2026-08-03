@extends('layouts.admin')

@section('title', 'Capability Templates')
@section('header', 'Capability Templates')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">Templates</h3>
    <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="btn-primary">Create Template</button>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Type</th>
                <th>Modules</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($templates as $template)
                <tr>
                    <td class="font-medium text-sm">{{ $template->name }}</td>
                    <td class="text-sm text-slate-400">{{ $template->slug }}</td>
                    <td class="text-sm">{{ $template->business_type ?? '-' }}</td>
                    <td class="text-sm">
                        @foreach($template->enabled_modules ?? [] as $module => $enabled)
                            @if($enabled)
                                <span class="badge badge-blue text-xs">{{ $module }}</span>
                            @endif
                        @endforeach
                    </td>
                    <td>
                        @if($template->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-yellow">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.capability-templates.show', $template) }}" class="text-blue-400 hover:text-blue-300">View</a>
                            <form method="POST" action="{{ route('admin.capability-templates.destroy', $template) }}" class="inline" data-confirm="Delete this template?">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No templates yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="glass-card rounded-lg p-6 w-full max-w-lg mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-white font-semibold">Create Template</h3>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.capability-templates.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name</label>
                    <input type="text" name="name" class="input-dark" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Business Type</label>
                    <input type="text" name="business_type" class="input-dark" placeholder="restaurant, salon...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea name="description" class="input-dark" rows="2"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Enabled Modules (JSON)</label>
                    <textarea name="enabled_modules" class="input-dark" rows="3" placeholder='{"catalog": true, "orders": true}'></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded" checked>
                    <label class="text-sm text-slate-300">Active</label>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="btn-primary">Create</button>
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>

@endsection
