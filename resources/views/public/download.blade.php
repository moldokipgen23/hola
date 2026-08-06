@extends('layouts.public')

@section('title', 'Download the Eiho One App')
@section('meta_description', 'Download the Eiho One app — discover local businesses, book appointments, stays, turf and more in Lamka / Churachandpur.')

@section('content')
<div class="max-w-2xl mx-auto py-16 px-4 text-center">
    <div class="w-20 h-20 mx-auto mb-6 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-10 h-10 text-white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
        </div>
    <h1 class="text-3xl font-bold text-white mb-2">Eiho One App</h1>
    <p class="text-slate-400 mb-2">Discover local businesses, book appointments, stays, turf and more.</p>
    <p class="text-sm text-slate-500 mb-8">Version {{ $version }} · Android</p>

    @if($apkExists)
        <a href="{{ asset('downloads/EihoOne.apk') }}" class="btn-primary inline-flex items-center gap-2 px-8 py-3 text-base">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            Download App ({{ $apkSize }})
        </a>
    @else
        <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 px-4 py-3 rounded-lg mb-4">The app is not available for download yet. Please check back soon.</div>
    @endif

    <div class="mt-10 glass-card p-6 rounded-xl text-left">
        <h3 class="text-white font-semibold mb-4">How to install</h3>
        <ol class="space-y-3 text-sm text-slate-300 list-decimal list-inside">
            <li>Tap <strong class="text-white">Download App</strong> above to get the APK file.</li>
            <li>Open the downloaded file on your Android phone.</li>
            <li>If asked, allow <strong class="text-white">"Install from unknown sources"</strong> (Settings → Security).</li>
            <li>Tap <strong class="text-white">Install</strong> and open Eiho One.</li>
        </ol>
    </div>

    <p class="text-xs text-slate-600 mt-8">The Eiho One app is currently available for Android. iOS version coming soon.</p>
</div>
@endsection
