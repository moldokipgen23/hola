@extends('layouts.public')

@section('title', 'All Categories | Hola - Churachandpur Directory')
@section('description', 'Browse all business categories in Churachandpur, Manipur')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">All Categories</h1>
        <p class="text-slate-500 text-sm">Browse businesses by category</p>
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-8">
    @php
        $categories = \App\Models\Category::active()->withCount('businesses')->orderByDesc('businesses_count')->get();
        $categoryIcons = [
            'food' => '🍽️', 'restaurant' => '🍽️', 'restaurants' => '🍽️',
            'healthcare' => '🏥', 'hospital' => '🏥', 'clinic' => '🏥', 'pharmacy' => '💊',
            'shopping' => '🛍️', 'store' => '🛍️', 'shops' => '🛍️',
            'hotel' => '🏨', 'hotels' => '🏨', 'lodge' => '🏨',
            'electronics' => '📱', 'mobile' => '📱',
            'beauty' => '💇', 'salon' => '💇', 'spa' => '💇',
            'bank' => '🏦', 'banks' => '🏦', 'finance' => '🏦',
            'education' => '🏫', 'school' => '🏫', 'college' => '🏫',
            'automotive' => '🚗', 'garage' => '🚗',
            'sports' => '⚽', 'gym' => '💪', 'fitness' => '💪',
            'professional' => '💼', 'office' => '💼',
            'church' => '⛪', 'churches' => '⛪', 'worship' => '⛪',
            'preschool' => '🧒', 'nursery' => '🧒',
            'services' => '🔧', 'repair' => '🔧',
            'general' => '📦',
        ];
        $defaultIcon = '📍';
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach($categories as $cat)
            @php
                $icon = $defaultIcon;
                foreach($categoryIcons as $key => $emoji) {
                    if(stripos($cat->name, $key) !== false) { $icon = $emoji; break; }
                }
            @endphp
            <a href="/category/{{ $cat->slug }}" class="category-card bg-white rounded-xl border border-slate-100 p-6 text-center hover:border-primary-200">
                <div class="text-4xl mb-3">{{ $icon }}</div>
                <h2 class="text-sm font-semibold text-slate-900">{{ $cat->name }}</h2>
                <p class="text-xs text-slate-400 mt-1">{{ $cat->businesses_count }} {{ Str::plural('business', $cat->businesses_count) }}</p>
            </a>
        @endforeach
    </div>
</div>
@endsection
