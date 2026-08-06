@extends('layouts.admin')

@section('title', 'Import by Link')
@section('header', 'Import by Link')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="glass-card p-6 rounded-xl mb-6">
        <h2 class="text-xl font-semibold text-white">Import a business from its Google Maps link</h2>
        <p class="text-slate-400 text-sm mt-2">
            Paste a business's <strong>Share</strong> link from Google Maps (e.g. a Kuki-run resthouse in Delhi,
            a hotel in Guwahati). We'll read the place, fetch its details, and add it to the review queue.
        </p>
        <div class="mt-4 rounded-lg bg-white/5 border border-white/10 p-3 text-xs text-slate-400">
            <strong>How to get the link:</strong> In Google Maps → open the business → tap <strong>Share</strong> → copy the link.
        </div>

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 rounded-lg mt-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.import.by-link.submit') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Google Maps link *</label>
                <textarea name="link" rows="2" class="input-dark w-full" required placeholder="https://maps.app.goo.gl/... or https://www.google.com/maps/place/...">{{ old('link') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">City (optional)</label>
                <select name="city_id" class="input-dark w-full">
                    <option value="">No city / let the AI decide</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" {{ old('city_id') == $city->id ? 'selected' : '' }}>
                            {{ $city->name }}{{ $city->state ? ' — '.$city->state : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Attaches the business to a curated city for Discovery/Booking.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary">Fetch & import</button>
                <a href="{{ route('admin.import') }}" class="btn-ghost">Back to imports</a>
            </div>
        </form>
    </div>

    @if($recent->isNotEmpty())
    <div class="glass-card rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-white/10">
            <h3 class="text-white font-semibold">Recently imported by link</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Status</th>
                    <th>Imported</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recent as $item)
                    <tr>
                        <td class="text-sm">{{ $item->data['name'] ?? 'Business' }}</td>
                        <td>
                            <span class="px-2 py-0.5 text-xs rounded-full {{ $item->status === 'approved' ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400' }}">
                                {{ ucfirst($item->status) }}
                            </span>
                        </td>
                        <td class="text-sm text-slate-500">{{ $item->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
