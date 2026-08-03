@extends('layouts.admin')

@section('title', 'Homepage CMS')
@section('header', 'Homepage CMS')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h3 class="text-white font-semibold text-lg">World Homepage Sections</h3>
    <a href="{{ route('admin.homepage.create') }}" class="btn-primary">Add Section</a>
</div>

<div class="glass-card rounded-lg overflow-hidden">
    <table class="data-table">
        <thead>
            <tr>
                <th>Sort</th>
                <th>World</th>
                <th>Type</th>
                <th>Title</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contents as $content)
                <tr data-id="{{ $content->id }}" class="sortable-row">
                    <td class="text-sm text-slate-500">
                        <span class="cursor-move drag-handle text-slate-400 hover:text-white">⠿</span>
                        <span class="ml-1">{{ $content->sort_order }}</span>
                    </td>
                    <td class="text-sm">{{ $content->world->name ?? '-' }}</td>
                    <td>
                        <span class="badge badge-blue">{{ $content->section_type }}</span>
                    </td>
                    <td class="font-medium text-sm">{{ $content->title }}</td>
                    <td>
                        @if($content->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-yellow">Inactive</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.homepage.update', $content) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="world_id" value="{{ $content->world_id }}">
                                <input type="hidden" name="section_type" value="{{ $content->section_type }}">
                                <input type="hidden" name="title" value="{{ $content->title }}">
                                <input type="hidden" name="subtitle" value="{{ $content->subtitle }}">
                                <input type="hidden" name="is_active" value="{{ $content->is_active ? '1' : '0' }}">
                                <button type="submit" class="text-slate-400 hover:text-white text-xs">
                                    {{ $content->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <a href="{{ route('admin.homepage.edit', $content) }}" class="text-blue-400 hover:text-blue-300">Edit</a>
                            <form method="POST" action="{{ route('admin.homepage.destroy', $content) }}" class="inline" data-confirm="Delete this section?">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-300">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-slate-400">No homepage sections yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
