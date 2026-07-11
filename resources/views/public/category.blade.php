@extends('layouts.public')

@section('title', $category->name . ' | Hola - Churachandpur Directory')
@section('description', "Browse {$category->name} businesses in Lamka, Churachandpur, Manipur, India")

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-3">
            <a href="/" class="hover:text-primary-600">Home</a>
            <span>/</span>
            <a href="/categories" class="hover:text-primary-600">Categories</a>
            <span>/</span>
            <span class="text-slate-600">{{ $category->name }}</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">{{ $category->name }}</h1>
        <p class="text-slate-500 text-sm">{{ $businesses->total() }} {{ Str::plural('business', $businesses->total()) }}</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($businesses as $biz)
            <a href="/business/{{ $biz->slug }}" class="business-card bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-primary-200">
                <div class="h-44 bg-slate-100 relative overflow-hidden">
                    @if(!empty($biz->photos) && is_array($biz->photos) && count($biz->photos) > 0)
                        <img src="{{ str_starts_with($biz->photos[0], 'http') ? $biz->photos[0] : asset($biz->photos[0]) }}" alt="{{ $biz->name }}" class="w-full h-full object-cover" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-50 to-accent-50">
                            <span class="text-4xl">📍</span>
                        </div>
                    @endif
                    @if($biz->claim_status === 'claimed')
                        <span class="absolute top-3 left-3 px-2 py-0.5 rounded-full bg-emerald-400 text-white text-xs font-semibold">Verified</span>
                    @endif
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <h3 class="text-sm font-semibold text-slate-900 truncate">{{ $biz->name }}</h3>
                        @if($biz->average_rating > 0)
                            <span class="flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs font-semibold whitespace-nowrap">
                                ★ {{ number_format($biz->average_rating, 1) }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-400 truncate mt-2">📍 {{ $biz->address ?: 'Churachandpur' }}</p>
                    @if($biz->phone)
                        <p class="text-xs text-slate-400 truncate mt-0.5">📞 {{ $biz->phone }}</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-16">
                <p class="text-4xl mb-3">📍</p>
                <p class="text-slate-500 text-lg font-medium">No businesses in this category yet</p>
                <a href="/categories" class="inline-block mt-4 px-4 py-2 rounded-lg bg-primary-50 text-primary-600 text-sm font-medium hover:bg-primary-100">Browse Categories</a>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $businesses->links() }}
    </div>
</div>
@endsection
