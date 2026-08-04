@extends('layouts.admin')

@section('title', 'App Features On/Off')
@section('header', 'App Features On/Off')

@section('content')
<div class="max-w-4xl mx-auto">
    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    <div class="glass-card p-6 rounded-xl mb-6">
        <h2 class="text-xl font-semibold text-white">What customers can use</h2>
        <p class="text-slate-400 text-sm mt-2">These are the only master switches. Turn one off and that whole area disappears from the website and app. Individual taxi, rental, hotel, turf, restaurant, and shop setup belongs inside each business.</p>
        <p class="text-slate-500 text-xs mt-3">To configure one business: <a class="text-purple-300 hover:text-purple-200" href="{{ route('admin.businesses') }}">Businesses → Modules</a>.</p>
    </div>

    <div class="space-y-4">
        @foreach($flags as $flag)
            @php
                $feature = $masterFeatures[$flag->key] ?? ['name' => $flag->key, 'description' => ''];
            @endphp
            <div class="glass-card p-5 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-white font-semibold">{{ $feature['name'] }}</h3>
                    <p class="text-slate-400 text-sm mt-1">{{ $feature['description'] }}</p>
                    <p class="text-xs mt-2 {{ $flag->is_enabled ? 'text-emerald-400' : 'text-slate-500' }}">{{ $flag->is_enabled ? 'Available to customers' : 'Hidden from customers' }}</p>
                </div>
                <form method="POST" action="{{ route('admin.feature-flags.toggle', $flag->id) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="min-w-28 px-4 py-2.5 rounded-lg text-sm font-semibold {{ $flag->is_enabled ? 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30' : 'bg-slate-700 text-slate-200 hover:bg-slate-600' }}">
                        {{ $flag->is_enabled ? 'On — turn off' : 'Off — turn on' }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
