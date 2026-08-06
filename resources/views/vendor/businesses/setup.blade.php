@extends('vendor.layouts.dashboard')

@section('title', 'What do you offer?')
@section('header', 'Setup Your Business')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">What do you offer?</h2>
        <p class="text-slate-400">Pick the closest match — you can fine-tune everything later in Business Features.</p>
    </div>

    <form method="POST" action="{{ route('vendor.businesses.setup.post', $business->id) }}">
        @csrf

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded mb-6">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="space-y-4 mb-8">
            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group block {{ old('offer') == 'sell' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="offer" value="sell" class="hidden" {{ old('offer') == 'sell' ? 'checked' : '' }}>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                        <span class="text-2xl">🛍️</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-white">I sell products</p>
                        <p class="text-xs text-slate-500 mt-1">Grocery, retail, restaurant, pharmacy — customers browse and send orders.</p>
                    </div>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group block {{ old('offer') == 'book' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="offer" value="book" class="hidden" {{ old('offer') == 'book' ? 'checked' : '' }}>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-pink-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                        <span class="text-2xl">📅</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-white">I take bookings</p>
                        <p class="text-xs text-slate-500 mt-1">Salon, hotel, turf, appointments — customers request times and slots.</p>
                        <p class="text-xs text-pink-300 mt-2">As a {{ $suggestion['category_name'] ?? 'service' }} business, we'll set up {{ str_replace('_', ' ', $suggestion['experience'] ?? 'appointment') }} booking for you. You can change this later.</p>
                    </div>
                </div>
            </label>

            <label class="glass-card p-5 rounded-xl cursor-pointer hover:border-purple-500/50 transition-all group block {{ old('offer') == 'list' ? 'border-purple-500 bg-purple-500/10' : '' }}">
                <input type="radio" name="offer" value="list" class="hidden" {{ old('offer') == 'list' ? 'checked' : '' }}>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-500/20 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                        <span class="text-2xl">📋</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-white">Just list me</p>
                        <p class="text-xs text-slate-500 mt-1">Basic directory listing with contact info — no orders or bookings.</p>
                    </div>
                </div>
            </label>
        </div>

        <div class="flex gap-3 justify-center">
            <button type="submit" class="btn-primary px-8">Continue</button>
            <a href="{{ route('vendor.dashboard') }}" class="btn-ghost">Skip for now</a>
        </div>
    </form>
</div>
@endsection
