@extends('layouts.admin')

@section('title', 'Taxonomy Suggestions')
@section('header', 'Taxonomy Suggestions')

@section('content')
<div class="flex flex-col gap-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <p class="text-slate-400">Autopilot can suggest taxonomy changes, but only an administrator can approve them.</p>
        </div>
        <div class="flex gap-2">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                <a href="{{ route('admin.taxonomy.suggestions', ['status' => $value]) }}"
                   class="{{ $status === $value ? 'btn-primary' : 'btn-ghost' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @forelse($suggestions as $suggestion)
        <div class="glass-card p-5">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge {{ $suggestion->status === 'pending' ? 'badge-yellow' : ($suggestion->status === 'approved' ? 'badge-green' : 'badge-red') }}">
                            {{ ucfirst($suggestion->status) }}
                        </span>
                        <span class="text-xs text-slate-500">{{ $suggestion->source_provider ?? 'AI' }} · {{ $suggestion->created_at->diffForHumans() }}</span>
                    </div>
                    <h3 class="text-lg font-semibold text-white">{{ $suggestion->suggested_name }}</h3>
                    <p class="text-sm text-slate-400 mt-1">
                        Source type: <span class="font-mono text-slate-300">{{ $suggestion->source_type ?? 'not supplied' }}</span>
                    </p>
                    @if($suggestion->importItem)
                        <p class="text-sm text-slate-400">Import: {{ $suggestion->importItem->data['name'] ?? 'Unknown business' }}</p>
                    @elseif($suggestion->business)
                        <p class="text-sm text-slate-400">Business: {{ $suggestion->business->name }}</p>
                    @endif
                </div>

                @if($suggestion->status === 'pending')
                    <form method="POST" action="{{ route('admin.taxonomy.suggestions.resolve', $suggestion) }}" class="w-full lg:max-w-2xl">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Map to approved category</label>
                                <select name="category_id" class="input-dark">
                                    <option value="">Select category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Or create approved category</label>
                                <input name="category_name" value="{{ $suggestion->suggested_name }}" class="input-dark">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-xs text-slate-400 mb-1">Optional approved subcategory</label>
                            <select name="subcategory_id" class="input-dark">
                                <option value="">No subcategory</option>
                                @foreach($categories as $category)
                                    <optgroup label="{{ $category->name }}">
                                        @foreach($category->subcategories as $subcategory)
                                            <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach(['catalog', 'orders', 'bookings', 'inventory', 'transport', 'turf'] as $module)
                                <label class="flex items-center gap-1.5 text-xs text-slate-300">
                                    <input type="checkbox" name="recommended_modules[]" value="{{ $module }}">
                                    {{ ucfirst($module) }}
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label class="block text-xs text-slate-400 mb-1">Review notes</label>
                            <textarea name="review_notes" rows="2" class="input-dark" placeholder="Why this mapping was approved or rejected"></textarea>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button name="action" value="map_existing" class="btn-primary">Map existing</button>
                            <button name="action" value="create_category" class="btn-ghost">Create approved category</button>
                            <button name="action" value="reject" class="btn-danger">Reject</button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-slate-500">Reviewed {{ $suggestion->reviewed_at?->diffForHumans() ?? 'previously' }}</p>
                @endif
            </div>
        </div>
    @empty
        <div class="glass-card p-12 text-center text-slate-400">No {{ $status }} taxonomy suggestions.</div>
    @endforelse

    {{ $suggestions->links() }}
</div>
@endsection
