@extends('layouts.admin')

@section('title', 'Settings')
@section('header', 'Settings')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')

    <!-- Tab Navigation -->
    <div class="flex gap-1 p-1 bg-white/5 rounded-xl mb-6 overflow-x-auto" id="settingsTabs">
        <button type="button" onclick="switchTab('general')" data-tab="general" class="settings-tab active">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            General
        </button>
        <button type="button" onclick="switchTab('seo')" data-tab="seo" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            SEO
        </button>
        <button type="button" onclick="switchTab('social')" data-tab="social" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
            Social Media
        </button>
        <button type="button" onclick="switchTab('contact')" data-tab="contact" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Contact
        </button>
        <button type="button" onclick="switchTab('smtp')" data-tab="smtp" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            SMTP / Email
        </button>
        <button type="button" onclick="switchTab('storage')" data-tab="storage" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            Storage
        </button>
        <button type="button" onclick="switchTab('api')" data-tab="api" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            API Keys
        </button>
        <button type="button" onclick="switchTab('notifications')" data-tab="notifications" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            Notifications
        </button>
    </div>

    <!-- Tab: General -->
    <div id="tab-general" class="tab-content">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">General Settings</h3>
                    <p class="text-slate-500 text-xs">Basic site configuration</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Site Name</label>
                    <input type="text" name="settings[site_name]" value="{{ $settings['site_name'] ?? 'Hola' }}" class="input-dark">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Tagline</label>
                    <input type="text" name="settings[tagline]" value="{{ $settings['tagline'] ?? '' }}" class="input-dark" placeholder="Your district guide">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">District</label>
                    <input type="text" name="settings[district]" value="{{ $settings['district'] ?? 'Churachandpur' }}" class="input-dark">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: SEO -->
    <div id="tab-seo" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">SEO Settings</h3>
                    <p class="text-slate-500 text-xs">Search engine optimization</p>
                </div>
            </div>
            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Title</label>
                    <input type="text" name="settings[meta_title]" value="{{ $settings['meta_title'] ?? '' }}" class="input-dark" placeholder="Lamka Directory">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Description</label>
                    <textarea name="settings[meta_description]" rows="3" class="input-dark" placeholder="Discover businesses in Lamka...">{{ $settings['meta_description'] ?? '' }}</textarea>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: Social Media -->
    <div id="tab-social" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-pink-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-pink-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Social Media</h3>
                    <p class="text-slate-500 text-xs">Social media links</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Facebook URL</label>
                    <input type="url" name="settings[facebook_url]" value="{{ $settings['facebook_url'] ?? '' }}" class="input-dark" placeholder="https://facebook.com/...">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Instagram URL</label>
                    <input type="url" name="settings[instagram_url]" value="{{ $settings['instagram_url'] ?? '' }}" class="input-dark" placeholder="https://instagram.com/...">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: Contact -->
    <div id="tab-contact" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Contact Information</h3>
                    <p class="text-slate-500 text-xs">How users can reach you</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Email</label>
                    <input type="email" name="settings[contact_email]" value="{{ $settings['contact_email'] ?? '' }}" class="input-dark" placeholder="hello@hola.app">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Phone</label>
                    <input type="text" name="settings[contact_phone]" value="{{ $settings['contact_phone'] ?? '' }}" class="input-dark" placeholder="+91 9876543210">
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: SMTP -->
    <div id="tab-smtp" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-orange-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">SMTP / Email Configuration</h3>
                    <p class="text-slate-500 text-xs">Configure email sending for verification, password reset, and notifications</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Mail Driver</label>
                    <select name="settings[smtp_driver]" class="input-dark">
                        <option value="smtp" {{ ($settings['smtp_driver'] ?? 'log') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                        <option value="log" {{ ($settings['smtp_driver'] ?? '') === 'log' ? 'selected' : '' }}>Log (Debug Only)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">From Email Address</label>
                    <input type="email" name="settings[smtp_from_address]" value="{{ $settings['smtp_from_address'] ?? '' }}" class="input-dark" placeholder="noreply@hola.app">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">From Name</label>
                    <input type="text" name="settings[smtp_from_name]" value="{{ $settings['smtp_from_name'] ?? 'Hola' }}" class="input-dark" placeholder="Hola">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">SMTP Host</label>
                    <input type="text" name="settings[smtp_host]" value="{{ $settings['smtp_host'] ?? '' }}" class="input-dark" placeholder="smtp.brevo.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">SMTP Port</label>
                    <input type="number" name="settings[smtp_port]" value="{{ $settings['smtp_port'] ?? '587' }}" class="input-dark" placeholder="587">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Encryption</label>
                    <select name="settings[smtp_encryption]" class="input-dark">
                        <option value="tls" {{ ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">SMTP Username</label>
                    <input type="text" name="settings[smtp_username]" value="{{ $settings['smtp_username'] ?? '' }}" class="input-dark" placeholder="Your SMTP login">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">SMTP Password</label>
                    <input type="password" name="settings[smtp_password]" value="{{ $settings['smtp_password'] ?? '' }}" class="input-dark" placeholder="Your SMTP password">
                </div>
            </div>

            <!-- Presets -->
            <div class="mt-5 p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                <p class="text-xs text-slate-400 mb-3 uppercase tracking-wider font-semibold">Quick Presets</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="fillBrevo()" class="px-3 py-1.5 text-xs rounded-lg bg-orange-500/10 text-orange-400 hover:bg-orange-500/20 transition">Brevo</button>
                    <button type="button" onclick="fillGmail()" class="px-3 py-1.5 text-xs rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition">Gmail</button>
                    <button type="button" onclick="fillSendgrid()" class="px-3 py-1.5 text-xs rounded-lg bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 transition">SendGrid</button>
                    <button type="button" onclick="fillResend()" class="px-3 py-1.5 text-xs rounded-lg bg-purple-500/10 text-purple-400 hover:bg-purple-500/20 transition">Resend</button>
                </div>
            </div>

            <!-- Test Email -->
            <div class="mt-5 flex gap-3">
                <input type="email" id="testEmail" placeholder="test@example.com" class="input-dark flex-1">
                <button type="button" onclick="sendTestEmail()" class="btn-primary px-6">Send Test Email</button>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: Storage -->
    <div id="tab-storage" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-cyan-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Cloud Storage (Bunny CDN)</h3>
                    <p class="text-slate-500 text-xs">Store business photos on Bunny CDN instead of VPS disk</p>
                </div>
            </div>

            <div class="p-4 bg-cyan-500/5 rounded-xl border border-cyan-500/20 mb-6">
                <p class="text-cyan-400 text-xs font-semibold mb-1">How to set up Bunny Storage:</p>
                <ol class="text-slate-500 text-xs space-y-1 list-decimal list-inside">
                    <li>Go to <a href="https://panel.bunny.net/" target="_blank" class="text-cyan-400 hover:underline">panel.bunny.net</a> → Storage → Access → copy the <strong>API / HTTP</strong> key</li>
                    <li>Click <strong>"+ Connect Pull Zone"</strong> to create a CDN endpoint (required for photos to load publicly)</li>
                    <li>Copy the Pull Zone URL (e.g. <code class="bg-slate-800 px-1 rounded">https://hola-storage1.b-cdn.net</code>) into the CDN URL field below</li>
                </ol>
            </div>

            <div class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Storage Zone Name</label>
                        <input type="text" name="settings[bunny_zone_name]" value="{{ $settings['bunny_zone_name'] ?? '' }}" class="input-dark" placeholder="hola-storage1">
                        <p class="text-slate-600 text-xs mt-1">The name you chose when creating the zone</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Storage Region</label>
                        <select name="settings[bunny_region]" class="input-dark">
                            <option value="sg" {{ ($settings['bunny_region'] ?? 'sg') === 'sg' ? 'selected' : '' }}>Singapore (SG)</option>
                            <option value="ny" {{ ($settings['bunny_region'] ?? '') === 'ny' ? 'selected' : '' }}>New York (NY)</option>
                            <option value="la" {{ ($settings['bunny_region'] ?? '') === 'la' ? 'selected' : '' }}>Los Angeles (LA)</option>
                            <option value="syd" {{ ($settings['bunny_region'] ?? '') === 'syd' ? 'selected' : '' }}>Sydney (SYD)</option>
                            <option value="br" {{ ($settings['bunny_region'] ?? '') === 'br' ? 'selected' : '' }}>Sao Paulo (BR)</option>
                            <option value="jh" {{ ($settings['bunny_region'] ?? '') === 'jh' ? 'selected' : '' }}>Johannesburg (JHB)</option>
                            <option value="ams" {{ ($settings['bunny_region'] ?? '') === 'ams' ? 'selected' : '' }}>Amsterdam (AMS)</option>
                        </select>
                        <p class="text-slate-600 text-xs mt-1">Must match the region you selected in Bunny.net</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Access Key (API Key)</label>
                        <input type="password" name="settings[bunny_access_key]" value="{{ $settings['bunny_access_key'] ?? '' }}" class="input-dark" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        <p class="text-slate-600 text-xs mt-1">Found under Access → API / HTTP tab in your storage zone</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">CDN URL</label>
                        <input type="url" name="settings[bunny_cdn_url]" value="{{ $settings['bunny_cdn_url'] ?? '' }}" class="input-dark" placeholder="https://hola-photos.b-cdn.net">
                        <p class="text-slate-600 text-xs mt-1">Your CDN zone URL (e.g. https://yourname.b-cdn.net)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Pull Zone URL (optional)</label>
                        <input type="url" name="settings[bunny_pull_zone_url]" value="{{ $settings['bunny_pull_zone_url'] ?? '' }}" class="input-dark" placeholder="https://photos.hola.ehlom.com">
                        <p class="text-slate-600 text-xs mt-1">Custom domain if you set one up (leave empty to use CDN URL)</p>
                    </div>
                </div>

                @if(($settings['bunny_zone_name'] ?? '') && ($settings['bunny_access_key'] ?? ''))
                <div class="p-3 bg-green-500/10 rounded-lg border border-green-500/20">
                    <p class="text-green-400 text-xs font-semibold">Bunny Storage is configured and active</p>
                </div>
                @else
                <div class="p-3 bg-yellow-500/10 rounded-lg border border-yellow-500/20">
                    <p class="text-yellow-400 text-xs font-semibold">Not configured yet — photos will be stored on VPS disk</p>
                </div>
                @endif
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: API Keys -->
    <div id="tab-api" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-yellow-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-yellow-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">API Keys</h3>
                    <p class="text-slate-500 text-xs">Global API keys for AI agents, maps, and search</p>
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-semibold text-slate-300 mb-3 uppercase tracking-wider">AI Providers</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">DeepSeek API Key
                                <a href="https://platform.deepseek.com/api_keys" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_deepseek]" value="{{ $settings['api_key_deepseek'] ?? '' }}" class="input-dark" placeholder="sk-...">
                            <p class="text-slate-600 text-xs mt-1">platform.deepseek.com — $0.27/M tokens</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">OpenAI API Key
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_openai]" value="{{ $settings['api_key_openai'] ?? '' }}" class="input-dark" placeholder="sk-...">
                            <p class="text-slate-600 text-xs mt-1">platform.openai.com — $2.50-$15/M tokens</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">OpenRouter API Key
                                <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_openrouter]" value="{{ $settings['api_key_openrouter'] ?? '' }}" class="input-dark" placeholder="sk-or-...">
                            <p class="text-slate-600 text-xs mt-1">openrouter.ai — multi-provider, pay per use</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Anthropic API Key
                                <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_anthropic]" value="{{ $settings['api_key_anthropic'] ?? '' }}" class="input-dark" placeholder="sk-ant-...">
                            <p class="text-slate-600 text-xs mt-1">console.anthropic.com — $3-$15/M tokens</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-6">
                    <h4 class="text-sm font-semibold text-slate-300 mb-3 uppercase tracking-wider">Maps & Search</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Google Maps / Places API Key
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_google_places]" value="{{ $settings['api_key_google_places'] ?? '' }}" class="input-dark" placeholder="AIza...">
                            <p class="text-slate-600 text-xs mt-1">console.cloud.google.com — enable Places API, Maps JS</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">SerpAPI Key
                                <a href="https://serpapi.com/manage-api-key" target="_blank" class="text-blue-400 hover:underline font-normal text-xs ml-1">Get Key →</a>
                            </label>
                            <input type="password" name="settings[api_key_serpapi]" value="{{ $settings['api_key_serpapi'] ?? '' }}" class="input-dark" placeholder="...">
                            <p class="text-slate-600 text-xs mt-1">serpapi.com — $50/mo (100 searches/mo free)</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: Notifications (FREE channels) -->
    <div id="tab-notifications" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-emerald-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Notification Channels (100% Free)</h3>
                    <p class="text-slate-500 text-xs">Configure free channels to notify business owners to claim their listing</p>
                </div>
            </div>

            <!-- Free Channel Info -->
            <div class="p-4 bg-emerald-500/5 rounded-xl border border-emerald-500/20 mb-6">
                <p class="text-emerald-400 text-xs font-semibold mb-2">Free channels available:</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span class="text-slate-300 text-xs"><strong>Email</strong> — Gmail SMTP (500/day free)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                        <span class="text-slate-300 text-xs"><strong>Telegram</strong> — Bot API (unlimited free)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                        <span class="text-slate-300 text-xs"><strong>WhatsApp</strong> — CallMeBot (free tier)</span>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Email Notifications -->
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-blue-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-white text-sm font-semibold">Email Notifications</h4>
                                <p class="text-slate-500 text-xs">Uses your SMTP settings above (Gmail = free)</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="settings[notify_email]" value="1" {{ ($settings['notify_email'] ?? '1') === '1' ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Notification Email (for admin alerts)</label>
                            <input type="email" name="settings[notify_email_address]" value="{{ $settings['notify_email_address'] ?? '' }}" class="input-dark" placeholder="admin@hola.app">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Claim Invitation Subject</label>
                            <input type="text" name="settings[notify_email_subject]" value="{{ $settings['notify_email_subject'] ?? 'Your business is on Hola - Claim it now!' }}" class="input-dark">
                        </div>
                    </div>
                </div>

                <!-- Telegram Bot (100% FREE) -->
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                                <svg fill="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-blue-400"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-white text-sm font-semibold">Telegram Bot <span class="text-emerald-400 text-xs">(100% FREE)</span></h4>
                                <p class="text-slate-500 text-xs">Unlimited messages, no credit card needed</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="settings[notify_telegram]" value="1" {{ ($settings['notify_telegram'] ?? '') === '1' ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                        </label>
                    </div>
                    <div class="p-3 bg-blue-500/5 rounded-lg border border-blue-500/20 mb-4">
                        <p class="text-blue-400 text-xs font-semibold mb-1">How to set up (2 minutes):</p>
                        <ol class="text-slate-500 text-xs space-y-1 list-decimal list-inside">
                            <li>Open Telegram, search for <strong>@BotFather</strong></li>
                            <li>Send <code class="bg-slate-800 px-1 rounded">/newbot</code> → choose a name → copy the <strong>bot token</strong></li>
                            <li>Start your bot, send a message, then visit: <code class="bg-slate-800 px-1 rounded">https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code></li>
                            <li>Copy your <strong>chat_id</code> from the response</li>
                        </ol>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Bot Token</label>
                            <input type="password" name="settings[telegram_bot_token]" value="{{ $settings['telegram_bot_token'] ?? '' }}" class="input-dark" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Chat ID (your phone number's chat)</label>
                            <input type="text" name="settings[telegram_chat_id]" value="{{ $settings['telegram_chat_id'] ?? '' }}" class="input-dark" placeholder="-1001234567890">
                            <p class="text-slate-600 text-xs mt-1">Use @userinfobot to find your chat_id</p>
                        </div>
                    </div>
                    <!-- Test Telegram -->
                    <div class="mt-4 flex gap-3">
                        <input type="text" id="testTelegramMsg" value="Hola! This is a test notification." class="input-dark flex-1">
                        <button type="button" onclick="sendTestTelegram()" class="btn-primary px-6">Send Test</button>
                    </div>
                </div>

                <!-- WhatsApp via CallMeBot (FREE) -->
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center">
                                <svg fill="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-green-400"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-white text-sm font-semibold">WhatsApp via CallMeBot <span class="text-yellow-400 text-xs">(Free tier)</span></h4>
                                <p class="text-slate-500 text-xs">Free for personal use, limited API calls</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="settings[notify_whatsapp]" value="1" {{ ($settings['notify_whatsapp'] ?? '') === '1' ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                    </div>
                    <div class="p-3 bg-yellow-500/5 rounded-lg border border-yellow-500/20 mb-4">
                        <p class="text-yellow-400 text-xs font-semibold mb-1">How to set up CallMeBot (free WhatsApp API):</p>
                        <ol class="text-slate-500 text-xs space-y-1 list-decimal list-inside">
                            <li>Save <strong>+34 644 71 81 96</strong> (CallMeBot) in your phone contacts</li>
                            <li>Open WhatsApp, send <code class="bg-slate-800 px-1 rounded">I allow callmebot to send me messages</code> to that number</li>
                            <li>You'll receive an API key — paste it below</li>
                            <li>Each business phone must also send this message to activate</li>
                        </ol>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">CallMeBot API Key</label>
                            <input type="password" name="settings[callmebot_api_key]" value="{{ $settings['callmebot_api_key'] ?? '' }}" class="input-dark" placeholder="Your CallMeBot API key">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Your WhatsApp Number (for admin alerts)</label>
                            <input type="text" name="settings[admin_whatsapp]" value="{{ $settings['admin_whatsapp'] ?? '' }}" class="input-dark" placeholder="919876543210">
                            <p class="text-slate-600 text-xs mt-1">With country code, no + sign</p>
                        </div>
                    </div>
                </div>

                <!-- Notification Schedule -->
                <div class="p-4 bg-slate-800/50 rounded-xl border border-slate-700/50">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4 text-purple-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-white text-sm font-semibold">Notification Schedule</h4>
                            <p class="text-slate-500 text-xs">When to send claim invitations to unclaimed businesses</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Days After Import</label>
                            <input type="number" name="settings[notify_days_after_import]" value="{{ $settings['notify_days_after_import'] ?? '3' }}" class="input-dark" min="1" max="30">
                            <p class="text-slate-600 text-xs mt-1">Wait this many days before notifying</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Max Notifications Per Day</label>
                            <input type="number" name="settings[notify_max_per_day]" value="{{ $settings['notify_max_per_day'] ?? '20' }}" class="input-dark" min="1" max="100">
                            <p class="text-slate-600 text-xs mt-1">Gmail free limit: 500/day</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2">Preferred Channel</label>
                            <select name="settings[notify_preferred_channel]" class="input-dark">
                                <option value="email" {{ ($settings['notify_preferred_channel'] ?? 'email') === 'email' ? 'selected' : '' }}>Email (Gmail - Free)</option>
                                <option value="telegram" {{ ($settings['notify_preferred_channel'] ?? '') === 'telegram' ? 'selected' : '' }}>Telegram (Free)</option>
                                <option value="whatsapp" {{ ($settings['notify_preferred_channel'] ?? '') === 'whatsapp' ? 'selected' : '' }}>WhatsApp (CallMeBot)</option>
                                <option value="all" {{ ($settings['notify_preferred_channel'] ?? '') === 'all' ? 'selected' : '' }}>All Channels</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Notification Settings</button>
            </div>
        </div>
    </div>
</form>

<style>
    .settings-tab {
        display: flex; align-items: center; gap: 6px;
        padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 500;
        color: #94a3b8; background: transparent; border: none; cursor: pointer;
        transition: all 0.2s ease; white-space: nowrap;
    }
    .settings-tab:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }
    .settings-tab.active {
        background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(168,85,247,0.15) 100%);
        color: #fff;
        box-shadow: 0 0 20px rgba(59,130,246,0.1);
    }
</style>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
}

function fill(field, value) {
    const el = document.querySelector(`[name="settings[${field}]"]`);
    if (el) el.value = value;
}

function fillBrevo() {
    fill('smtp_driver', 'smtp');
    fill('smtp_host', 'smtp-relay.brevo.com');
    fill('smtp_port', '587');
    fill('smtp_encryption', 'tls');
}

function fillGmail() {
    fill('smtp_driver', 'smtp');
    fill('smtp_host', 'smtp.gmail.com');
    fill('smtp_port', '587');
    fill('smtp_encryption', 'tls');
}

function fillSendgrid() {
    fill('smtp_driver', 'smtp');
    fill('smtp_host', 'smtp.sendgrid.net');
    fill('smtp_port', '587');
    fill('smtp_encryption', 'tls');
}

function fillResend() {
    fill('smtp_driver', 'smtp');
    fill('smtp_host', 'smtp.resend.com');
    fill('smtp_port', '587');
    fill('smtp_encryption', 'tls');
}

async function sendTestEmail() {
    const email = document.getElementById('testEmail').value;
    if (!email) {
        alert('Please enter a test email address');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Sending...';

    try {
        const resp = await fetch('{{ route("admin.settings.test-email") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ email }),
        });

        const data = await resp.json();
        alert(data.message || (resp.ok ? 'Test email sent!' : 'Failed to send'));
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Send Test Email';
    }
}

async function sendTestTelegram() {
    const msg = document.getElementById('testTelegramMsg').value;
    if (!msg) {
        alert('Please enter a test message');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Sending...';

    try {
        const resp = await fetch('/admin/settings/test-telegram', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message: msg }),
        });

        const data = await resp.json();
        alert(data.message || (resp.ok ? 'Test Telegram sent!' : 'Failed to send'));
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Send Test';
    }
}
</script>
@endsection
