@extends('layouts.public')

@section('title', 'Verify Claim | Hola')

@section('content')
<div class="bg-white border-b border-slate-100">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-3">
            <a href="/" class="hover:text-primary-600">Home</a>
            <span>/</span>
            <a href="/business/{{ $business->slug }}" class="hover:text-primary-600">{{ $business->name }}</a>
            <span>/</span>
            <span class="text-slate-600">Verify</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Verify Your Identity</h1>
        <p class="text-slate-500 text-sm">Enter the 6-digit code we sent to your WhatsApp/email.</p>
    </div>
</div>

<div class="max-w-2xl mx-auto px-4 py-8">
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-700 text-sm">{{ session('error') }}</p>
        </div>
    @endif
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-6">
            <p class="text-emerald-700 text-sm">{{ session('success') }}</p>
        </div>
    @endif

    <div class="max-w-md mx-auto">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-full bg-primary-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h2 class="text-lg font-semibold text-slate-900">Verification Code</h2>
                <p class="text-sm text-slate-500 mt-1">Check your WhatsApp or email for the code</p>
            </div>

            <form method="POST" action="{{ route('public.claim.verify.submit', $business->id) }}">
                @csrf
                <div class="mb-4">
                    <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus
                        class="w-full text-center text-2xl tracking-[0.5em] font-mono rounded-lg border border-slate-200 px-4 py-4 text-slate-900 focus:outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-100"
                        placeholder="000000">
                    @error('otp')<p class="text-red-500 text-xs mt-2 text-center">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full px-4 py-3 rounded-lg bg-primary-500 text-white text-sm font-semibold hover:bg-primary-600 transition-colors">Verify & Submit Claim</button>
            </form>

            <div class="mt-4 text-center">
                <form method="POST" action="{{ route('public.claim.resend-otp', $business->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-primary-600 hover:text-primary-700 font-medium">Resend Code</button>
                </form>
                <span class="text-slate-300 mx-2">|</span>
                <a href="{{ route('public.claim', $business->id) }}" class="text-sm text-slate-500 hover:text-slate-700">Change Number</a>
            </div>
        </div>

        <div class="mt-6 text-center">
            <p class="text-xs text-slate-400">Code expires in 10 minutes. Check your spam folder if you don't see it.</p>
        </div>
    </div>
</div>
@endsection
