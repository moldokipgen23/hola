@extends('layouts.public')

@section('title', $category->name . ' | ' . config('app.name', 'Hola'))
@section('description', "Browse {$category->name} businesses in Lamka, Churachandpur, Manipur, India")

@section('content')
<div class="text-sm text-slate-500 mb-4">
    <a href="/" class="hover:text-white">Home</a>
    <span class="mx-2">›</span>
    <a href="/categories" class="hover:text-white">Categories</a>
    <span class="mx-2">›</span>
    <span class="text-white">{{ $category->name }}</span>
</div>

<h1 class="text-3xl font-bold text-white mb-2">{{ $category->name }}</h1>
<p class="text-slate-400 mb-6">{{ $businesses->total() }} businesses</p>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @forelse($businesses as $business)
        <a href="/business/{{ $business->slug }}" class="glass-card p-4 hover:border-blue-500/30 transition">
            @if($business->photos && count($business->photos) > 0)
                <div class="h-40 rounded-xl overflow-hidden mb-3 bg-slate-800">
                    <img src="{{ asset($business->photos[0]) }}" alt="{{ $business->name }}" class="w-full h-full object-cover" loading="lazy">
                </div>
            @endif
            <h3 class="text-white font-semibold">{{ $business->name }}</h3>
            <p class="text-slate-500 text-sm">{{ $business->address }}</p>
            @if($business->average_rating)
                <div class="flex items-center gap-1 mt-2">
                    <span class="text-yellow-400">★</span>
                    <span class="text-white text-sm">{{ $business->average_rating }}</span>
                    <span class="text-slate-500 text-sm">({{ $business->review_count }})</span>
                </div>
            @endif
        </a>
    @empty
        <div class="col-span-3 text-center py-12">
            <p class="text-slate-500">No businesses in this category yet.</p>
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $businesses->links() }}
</div>
@endsection
