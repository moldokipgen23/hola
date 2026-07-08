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
        <button type="button" onclick="switchTab('api')" data-tab="api" class="settings-tab">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            API Keys
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
</script>
@endsection
