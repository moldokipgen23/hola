@extends('layouts.admin')

@section('title', $template->name)
@section('header', 'Template: ' . $template->name)

@section('content')
<div class="max-w-4xl space-y-6">
    <!-- Template Details -->
    <div class="glass-card rounded-lg p-6">
        <h3 class="text-white font-semibold mb-4">Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-slate-500">Name:</span>
                <span class="text-white ml-2">{{ $template->name }}</span>
            </div>
            <div>
                <span class="text-slate-500">Slug:</span>
                <span class="text-slate-300 ml-2">{{ $template->slug }}</span>
            </div>
            <div>
                <span class="text-slate-500">Business Type:</span>
                <span class="text-slate-300 ml-2">{{ $template->business_type ?? '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500">Status:</span>
                @if($template->is_active)
                    <span class="badge badge-green ml-2">Active</span>
                @else
                    <span class="badge badge-yellow ml-2">Inactive</span>
                @endif
            </div>
        </div>
        @if($template->description)
            <p class="text-slate-400 text-sm mt-4">{{ $template->description }}</p>
        @endif
        <div class="mt-4">
            <span class="text-slate-500 text-sm">Enabled Modules:</span>
            <div class="flex flex-wrap gap-2 mt-1">
                @foreach($template->enabled_modules ?? [] as $module => $enabled)
                    @if($enabled)
                        <span class="badge badge-blue">{{ $module }}</span>
                    @endif
                @endforeach
                @if(empty($template->enabled_modules))
                    <span class="text-slate-500 text-sm">None</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Assign to Business -->
    <div class="glass-card rounded-lg p-6">
        <h3 class="text-white font-semibold mb-4">Assign to Business</h3>
        <form method="POST" action="{{ route('admin.capability-templates.assign', $template) }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-300 mb-1">Business</label>
                <select name="business_id" class="input-dark" required>
                    <option value="">Select Business</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}">{{ $business->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary">Assign</button>
        </form>
    </div>
</div>

@endsection
