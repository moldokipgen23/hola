@extends('layouts.public')

@section('title', 'Browse by Area | Hola - Churachandpur Directory')
@section('description', 'Explore businesses by neighborhood in Churachandpur, Manipur')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Explore by Area</h1>
        <p class="text-slate-500 text-sm">{{ $areas->count() }} areas in Churachandpur</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($areas as $area)
            <a href="/area/{{ $area->slug }}" class="area-card bg-white rounded-xl border border-slate-100 p-6 hover:border-primary-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ $area->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $area->business_count }} {{ Str::plural('business', $area->business_count) }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endsection
