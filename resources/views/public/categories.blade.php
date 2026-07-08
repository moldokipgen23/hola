@extends('layouts.public')

@section('title', 'Categories | ' . config('app.name', 'Hola'))
@section('description', 'Browse all business categories in Lamka, Churachandpur, Manipur, India')

@section('content')
<h1 class="text-3xl font-bold text-white mb-6">Categories</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach(\App\Models\Category::withCount('businesses')->orderBy('name')->get() as $category)
        <a href="/category/{{ $category->slug }}" class="glass-card p-6 text-center hover:border-blue-500/30 transition">
            <div class="text-4xl mb-3">
                @if($category->image)
                    <img src="{{ asset($category->image) }}" alt="{{ $category->name }}" class="w-10 h-10 mx-auto object-contain">
                @else
                    {{ $category->icon ?? '📂' }}
                @endif
            </div>
            <h3 class="text-white font-semibold mb-1">{{ $category->name }}</h3>
            <p class="text-slate-500 text-sm">{{ $category->businesses_count }} businesses</p>
        </a>
    @endforeach
</div>
@endsection
