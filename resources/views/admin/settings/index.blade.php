@extends('layouts.admin')

@section('title', 'Settings')
@section('header', 'Settings')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- General -->
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">General</h3>
                    <p class="text-slate-500 text-xs">Basic site configuration</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Site Name</label>
                    <input type="text" name="settings[site_name]" value="{{ $settings['site_name'] ?? 'Hola' }}"
                        class="input-dark">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Tagline</label>
                    <input type="text" name="settings[tagline]" value="{{ $settings['tagline'] ?? '' }}"
                        class="input-dark" placeholder="Your district guide">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">District</label>
                    <input type="text" name="settings[district]" value="{{ $settings['district'] ?? 'Churachandpur' }}"
                        class="input-dark">
                </div>
            </div>
        </div>

        <!-- SEO -->
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">SEO</h3>
                    <p class="text-slate-500 text-xs">Search engine optimization</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Title</label>
                    <input type="text" name="settings[meta_title]" value="{{ $settings['meta_title'] ?? '' }}"
                        class="input-dark" placeholder="Lamka Directory">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Description</label>
                    <textarea name="settings[meta_description]" rows="3"
                        class="input-dark" placeholder="Discover businesses in Lamka...">{{ $settings['meta_description'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <!-- Social -->
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-pink-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-pink-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Social Media</h3>
                    <p class="text-slate-500 text-xs">Social links</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Facebook URL</label>
                    <input type="url" name="settings[facebook_url]" value="{{ $settings['facebook_url'] ?? '' }}"
                        class="input-dark" placeholder="https://facebook.com/...">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Instagram URL</label>
                    <input type="url" name="settings[instagram_url]" value="{{ $settings['instagram_url'] ?? '' }}"
                        class="input-dark" placeholder="https://instagram.com/...">
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Contact</h3>
                    <p class="text-slate-500 text-xs">Contact information</p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Email</label>
                    <input type="email" name="settings[contact_email]" value="{{ $settings['contact_email'] ?? '' }}"
                        class="input-dark" placeholder="hello@hola.app">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Phone</label>
                    <input type="text" name="settings[contact_phone]" value="{{ $settings['contact_phone'] ?? '' }}"
                        class="input-dark" placeholder="+91 9876543210">
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn-primary">Save Settings</button>
    </div>
</form>
@endsection
