@extends('layouts.public')

@section('title', 'Hola - Local Business Directory | Lamka, Churachandpur, Manipur')
@section('description', 'Discover local businesses in Lamka, Churachandpur, Manipur, India. Find restaurants, shops, services and more.')
@section('og_title', 'Hola - Local Business Directory | Lamka, Churachandpur, Manipur')
@section('og_description', 'Discover local businesses in Lamka, Churachandpur, Manipur, India. Find restaurants, shops, services and more.')

@section('content')
<!-- Hero -->
<div class="text-center py-16">
    <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
        Discover Local Businesses in<br>
        <span class="bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">Lamka, Churachandpur</span>
    </h1>
    <p class="text-slate-400 text-lg mb-8 max-w-2xl mx-auto">
        Find the best restaurants, shops, services, and businesses in your area. Powered by the community.
    </p>
    <div class="flex justify-center gap-4">
        <a href="/businesses" class="btn-primary">Browse Businesses</a>
        <a href="/categories" class="px-6 py-3 rounded-xl border border-white/10 text-white hover:bg-white/5 transition">View Categories</a>
    </div>
</div>

<!-- Categories -->
<div class="mb-12">
    <h2 class="text-2xl font-bold text-white mb-6">Browse by Category</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach(\App\Models\Category::withCount('businesses')->orderBy('name')->limit(8)->get() as $category)
            <a href="/category/{{ $category->slug }}" class="glass-card p-4 text-center hover:border-blue-500/30 transition">
                <div class="text-3xl mb-2">
                    @if($category->image)
                        <img src="{{ asset($category->image) }}" alt="{{ $category->name }}" class="w-8 h-8 mx-auto object-contain">
                    @else
                        {{ $category->icon ?? '📂' }}
                    @endif
                </div>
                <p class="text-white font-medium">{{ $category->name }}</p>
                <p class="text-slate-500 text-sm">{{ $category->businesses_count }} businesses</p>
            </a>
        @endforeach
    </div>
</div>

<!-- Featured Businesses -->
<div class="mb-12">
    <h2 class="text-2xl font-bold text-white mb-6">Featured Businesses</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(\App\Models\Business::where('is_featured', true)->where('is_active', true)->with('category')->limit(6)->get() as $business)
            <a href="/business/{{ $business->slug }}" class="glass-card p-4 hover:border-blue-500/30 transition">
                @if($business->photos && count($business->photos) > 0)
                    <div class="h-40 rounded-xl overflow-hidden mb-3 bg-slate-800">
                        <img src="{{ asset($business->photos[0]) }}" alt="{{ $business->name }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <h3 class="text-white font-semibold">{{ $business->name }}</h3>
                <p class="text-slate-500 text-sm">{{ $business->address }}</p>
                @if($business->average_rating)
                    <div class="flex items-center gap-1 mt-2">
                        <span class="text-yellow-400">★</span>
                        <span class="text-white text-sm">{{ $business->average_rating }}</span>
                        <span class="text-slate-500 text-sm">({{ $business->review_count }} reviews)</span>
                    </div>
                @endif
            </a>
        @endforeach
    </div>
</div>

<!-- Recent Businesses -->
<div>
    <h2 class="text-2xl font-bold text-white mb-6">Recently Added</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach(\App\Models\Business::where('is_active', true)->with('category')->latest()->limit(6)->get() as $business)
            <a href="/business/{{ $business->slug }}" class="glass-card p-4 hover:border-blue-500/30 transition">
                <h3 class="text-white font-semibold">{{ $business->name }}</h3>
                <p class="text-slate-500 text-sm">{{ $business->address }}</p>
                @if($business->category)
                    <span class="inline-block mt-2 px-2 py-0.5 text-xs rounded-full bg-blue-500/10 text-blue-400">{{ $business->category->name }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
@endsection
