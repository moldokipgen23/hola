@extends('layouts.public')

@section('title', 'All Businesses | ' . config('app.name', 'Hola'))
@section('description', 'Browse all local businesses in Lamka, Churachandpur, Manipur, India')

@section('content')
<h1 class="text-3xl font-bold text-white mb-6">All Businesses</h1>

<!-- Search & Filters -->
<form method="GET" class="glass-card p-4 rounded-xl mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search businesses..."
                class="w-full bg-slate-800/50 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:border-blue-500 outline-none">
        </div>
        <div>
            <select name="category" class="w-full bg-slate-800/50 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:border-blue-500 outline-none">
                <option value="">All Categories</option>
                @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                    <option value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="w-full btn-primary">Search</button>
        </div>
    </div>
</form>

<!-- Results -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @forelse($businesses as $business)
        <a href="/business/{{ $business->slug }}" class="glass-card p-4 hover:border-blue-500/30 transition">
            @if($business->photos && count($business->photos) > 0)
                <div class="h-40 rounded-xl overflow-hidden mb-3 bg-slate-800">
                    <img src="{{ str_starts_with($business->photos[0], 'http') ? $business->photos[0] : asset($business->photos[0]) }}" alt="{{ $business->name }}" class="w-full h-full object-cover" loading="lazy">
                </div>
            @endif
            <h3 class="text-white font-semibold">{{ $business->name }}</h3>
            <p class="text-slate-500 text-sm">{{ $business->address }}</p>
            <div class="flex items-center gap-2 mt-2">
                @if($business->category)
                    <span class="px-2 py-0.5 text-xs rounded-full bg-blue-500/10 text-blue-400">{{ $business->category->name }}</span>
                @endif
                @if($business->average_rating)
                    <span class="text-yellow-400 text-sm">★ {{ $business->average_rating }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="col-span-3 text-center py-12">
            <p class="text-slate-500">No businesses found.</p>
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $businesses->withQueryString()->links() }}
</div>
@endsection
