@extends('layouts.admin')

@section('title', 'Business Types')
@section('header', 'Business Types')

@section('content')
@php
    $styles = [
        'directory' => ['icon' => '📍', 'accent' => 'text-sky-300', 'border' => 'border-sky-400/20', 'button' => 'bg-sky-500/15 text-sky-200 hover:bg-sky-500/25', 'action' => 'Customers explore, call, message, or visit.'],
        'booking' => ['icon' => '📅', 'accent' => 'text-amber-300', 'border' => 'border-amber-400/20', 'button' => 'bg-amber-500/15 text-amber-200 hover:bg-amber-500/25', 'action' => 'Customers send a booking or availability request.'],
        'shopping' => ['icon' => '🛍️', 'accent' => 'text-emerald-300', 'border' => 'border-emerald-400/20', 'button' => 'bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25', 'action' => 'Customers browse a menu or products and send a COD order.'],
    ];
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="rounded-2xl border border-white/10 bg-gradient-to-r from-slate-900 to-slate-800 p-6 md:p-8">
        <p class="text-xs uppercase tracking-[0.18em] text-purple-300 font-semibold">Eiho One setup</p>
        <h2 class="mt-2 text-2xl font-semibold text-white">Choose how a business serves customers.</h2>
        <p class="mt-2 max-w-3xl text-sm text-slate-300">There are only three paths: Directory, Booking, and Shopping. Add a category beneath the right path, then add a business type only when it needs a different customer experience.</p>
    </div>

    @foreach($groups as $key => $group)
        @php
            $style = $styles[$key];
            $businessCount = $group['categories']->sum('businesses_count');
            $categoryCount = $group['categories']->count();
        @endphp
        <section class="rounded-2xl border {{ $style['border'] }} bg-slate-900/60 overflow-hidden">
            <div class="p-5 md:p-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-2xl">{{ $style['icon'] }}</div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xl font-semibold text-white">{{ $group['title'] }}</h3>
                            <span class="text-xs text-slate-400">{{ $categoryCount }} categories · {{ $businessCount }} businesses</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-400">{{ $style['action'] }}</p>
                    </div>
                </div>
                <a href="{{ route('admin.categories.create') }}?module_type={{ $key === 'shopping' ? 'ordering' : $key }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium transition {{ $style['button'] }}">+ Add category</a>
            </div>

            <div class="border-t border-white/5 p-4 md:p-5 grid grid-cols-1 lg:grid-cols-2 gap-3">
                @forelse($group['categories'] as $category)
                    <article class="rounded-xl border border-white/5 bg-white/[0.03] p-4 hover:bg-white/[0.05] transition">
                        <div class="flex gap-3 justify-between">
                            <div class="min-w-0">
                                <h4 class="font-medium text-white">{{ $category->icon ?: '•' }} {{ $category->name }}</h4>
                                <p class="mt-1 text-xs text-slate-500">{{ $category->businesses_count }} listed businesses{{ $category->is_active ? '' : ' · hidden' }}</p>
                            </div>
                            <a href="{{ route('admin.categories.edit', $category->id) }}" class="text-xs text-slate-400 hover:text-white shrink-0">Edit</a>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 min-h-7">
                            @forelse($category->subcategories->take(7) as $subcategory)
                                <a href="{{ route('admin.subcategories.edit', $subcategory->id) }}" class="rounded-lg bg-white/5 px-2.5 py-1 text-xs text-slate-300 hover:bg-white/10">{{ $subcategory->icon }} {{ $subcategory->name }}</a>
                            @empty
                                <span class="text-xs text-slate-500">No business types yet.</span>
                            @endforelse
                            @if($category->subcategories->count() > 7)
                                <a href="{{ route('admin.subcategories') }}" class="rounded-lg bg-white/5 px-2.5 py-1 text-xs text-slate-400">+{{ $category->subcategories->count() - 7 }} more</a>
                            @endif
                        </div>
                        <a href="{{ route('admin.subcategories.create') }}?category_id={{ $category->id }}" class="mt-4 inline-block text-xs {{ $style['accent'] }} hover:text-white">+ Add business type</a>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-white/10 p-5 text-sm text-slate-500">{{ $group['empty'] }}</div>
                @endforelse
            </div>
        </section>
    @endforeach

    <div class="rounded-xl bg-slate-900/60 px-5 py-4 text-sm text-slate-400">
        <span class="text-white font-medium">Keep it simple:</span> Taxi is a business type; vehicles are added inside the taxi business. Turf is a business type; courts and time slots are added inside the turf business. Restaurant is a business type; dishes are added inside the restaurant.
    </div>
</div>
@endsection
