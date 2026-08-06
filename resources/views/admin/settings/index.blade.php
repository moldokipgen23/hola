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
            General & Branding
        </button>
        <button type="button" onclick="switchTab('payment')" data-tab="payment" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            Payments
        </button>
        <button type="button" onclick="switchTab('smtp')" data-tab="smtp" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Email / SMTP
        </button>
        <button type="button" onclick="switchTab('api')" data-tab="api" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            APIs & Gateways
        </button>
        <button type="button" onclick="switchTab('storage')" data-tab="storage" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
            Storage
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
                    <input type="text" name="settings[site_name]" value="{{ $settings['site_name'] ?? 'Eiho One' }}" class="input-dark">
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

            <!-- Branding: logo + favicon -->
            <div class="mt-8 border-t border-white/10 pt-6">
                <h4 class="text-white font-semibold mb-1">Branding</h4>
                <p class="text-slate-500 text-xs mb-4">Upload your logo and favicon — shown across the site and app.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Logo</label>
                        <div class="flex items-center gap-3">
                            @if(!empty($settings['logo_url']))
                                <img src="{{ asset($settings['logo_url']) }}" class="h-12 object-contain bg-white/5 rounded border border-white/10 p-1" alt="logo">
                            @endif
                            <form method="POST" action="{{ route('admin.settings.upload') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
                                @csrf
                                <input type="hidden" name="type" value="logo">
                                <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="text-xs text-slate-400 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:bg-blue-500/10 file:text-blue-300 file:border-0">
                                <button class="btn-primary text-xs px-3 py-2">Upload</button>
                            </form>
                        </div>
                        <input type="text" name="settings[logo_url]" value="{{ $settings['logo_url'] ?? '' }}" class="input-dark mt-2" placeholder="or paste logo URL">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Favicon</label>
                        <div class="flex items-center gap-3">
                            @if(!empty($settings['favicon_url']))
                                <img src="{{ asset($settings['favicon_url']) }}" class="h-10 w-10 object-contain bg-white/5 rounded border border-white/10 p-1" alt="favicon">
                            @endif
                            <form method="POST" action="{{ route('admin.settings.upload') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
                                @csrf
                                <input type="hidden" name="type" value="favicon">
                                <input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon" class="text-xs text-slate-400 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:bg-blue-500/10 file:text-blue-300 file:border-0">
                                <button class="btn-primary text-xs px-3 py-2">Upload</button>
                            </form>
                        </div>
                        <input type="text" name="settings[favicon_url]" value="{{ $settings['favicon_url'] ?? '' }}" class="input-dark mt-2" placeholder="or paste favicon URL">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Footer Text</label>
                        <input type="text" name="settings[footer_text]" value="{{ $settings['footer_text'] ?? '' }}" class="input-dark" placeholder="© 2026 Eiho One. All rights reserved.">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Title (SEO)</label>
                        <input type="text" name="settings[meta_title]" value="{{ $settings['meta_title'] ?? $settings['site_name'] ?? 'Eiho One' }}" class="input-dark">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Meta Description (SEO)</label>
                        <textarea name="settings[meta_description]" rows="2" class="input-dark">{{ $settings['meta_description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Social links -->
            <div class="mt-8 border-t border-white/10 pt-6">
                <h4 class="text-white font-semibold mb-4">Social Media & Footer</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Facebook URL</label>
                        <input type="text" name="settings[facebook_url]" value="{{ $settings['facebook_url'] ?? '' }}" class="input-dark" placeholder="https://facebook.com/...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Instagram URL</label>
                        <input type="text" name="settings[instagram_url]" value="{{ $settings['instagram_url'] ?? '' }}" class="input-dark" placeholder="https://instagram.com/...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Phone</label>
                        <input type="text" name="settings[contact_phone]" value="{{ $settings['contact_phone'] ?? '' }}" class="input-dark">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Contact Email</label>
                        <input type="text" name="settings[contact_email]" value="{{ $settings['contact_email'] ?? '' }}" class="input-dark">
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    <!-- Tab: SMTP (Email) -->

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
                    <input type="text" name="settings[smtp_from_name]" value="{{ $settings['smtp_from_name'] ?? 'Eiho One' }}" class="input-dark" placeholder="Eiho One">
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

    <!-- Tab: Payment -->
    <div id="tab-payment" class="tab-content" style="display:none">
        <div class="glass-card p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5 text-green-400"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div>
                    <h3 class="text-white font-semibold">Payment Gateways</h3>
                    <p class="text-slate-500 text-xs">Configure payment methods available on the platform</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h4 class="text-amber-300 font-medium">Future online-payment launch switch</h4>
                            <p class="text-amber-200/70 text-xs mt-1">Keep this off during the free offline/COD launch. Razorpay and Cashfree configuration remains preserved below.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="hidden" name="settings[payment_online_enabled]" value="0">
                            <input type="checkbox" name="settings[payment_online_enabled]" value="1" class="sr-only peer" {{ ($settings['payment_online_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>

                <!-- COD -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h4 class="text-white font-medium">Cash on Delivery</h4>
                            <p class="text-slate-400 text-xs">Customers pay in cash when order is delivered</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[payment_cod_enabled]" value="0">
                            <input type="checkbox" name="settings[payment_cod_enabled]" value="1" class="sr-only peer" {{ ($settings['payment_cod_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                </div>

                <!-- Razorpay -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-white font-medium">Razorpay</h4>
                            <p class="text-slate-400 text-xs">Online payments via UPI, cards, netbanking</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[payment_razorpay_enabled]" value="0">
                            <input type="checkbox" name="settings[payment_razorpay_enabled]" value="1" class="sr-only peer" {{ ($settings['payment_razorpay_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Key ID</label>
                            <input type="text" name="settings[payment_razorpay_key_id]" value="{{ $settings['payment_razorpay_key_id'] ?? env('RAZORPAY_KEY_ID', '') }}" class="input-dark" placeholder="rzp_live_xxxxx">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Key Secret</label>
                            <input type="password" name="settings[payment_razorpay_key_secret]" value="{{ $settings['payment_razorpay_key_secret'] ?? env('RAZORPAY_KEY_SECRET', '') }}" class="input-dark" placeholder="xxxxxxxxxxxx">
                        </div>
                    </div>
                </div>

                <!-- Cashfree -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-white font-medium">Cashfree</h4>
                            <p class="text-slate-400 text-xs">Online payments via UPI, cards, netbanking, Paylater</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="settings[payment_cashfree_enabled]" value="0">
                            <input type="checkbox" name="settings[payment_cashfree_enabled]" value="1" class="sr-only peer" {{ ($settings['payment_cashfree_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">App ID</label>
                            <input type="text" name="settings[payment_cashfree_app_id]" value="{{ $settings['payment_cashfree_app_id'] ?? env('CASHFREE_APP_ID', '') }}" class="input-dark" placeholder="CF12345...">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Secret Key</label>
                            <input type="password" name="settings[payment_cashfree_secret_key]" value="{{ $settings['payment_cashfree_secret_key'] ?? env('CASHFREE_SECRET_KEY', '') }}" class="input-dark" placeholder="sk_xxxxx">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Environment</label>
                            <select name="settings[payment_cashfree_env]" class="input-dark">
                                <option value="TEST" {{ ($settings['payment_cashfree_env'] ?? env('CASHFREE_ENV', 'TEST')) == 'TEST' ? 'selected' : '' }}>TEST (Sandbox)</option>
                                <option value="PRODUCTION" {{ ($settings['payment_cashfree_env'] ?? env('CASHFREE_ENV', 'TEST')) == 'PRODUCTION' ? 'selected' : '' }}>PRODUCTION (Live)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Default -->
                <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Default Payment Method (for new vendors)</label>
                    <select name="settings[payment_default]" class="input-dark">
                        <option value="cod" {{ ($settings['payment_default'] ?? 'cod') == 'cod' ? 'selected' : '' }}>COD</option>
                        <option value="razorpay" {{ ($settings['payment_default'] ?? 'cod') == 'razorpay' ? 'selected' : '' }}>Razorpay</option>
                        <option value="cashfree" {{ ($settings['payment_default'] ?? 'cod') == 'cashfree' ? 'selected' : '' }}>Cashfree</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="btn-primary">Save Payment Settings</button>
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

// Open the right tab when landing on #payment, #seo, etc.
(function () {
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) {
        switchTab(hash);
    }
})();

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
