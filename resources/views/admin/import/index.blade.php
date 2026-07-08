@extends('layouts.admin')

@section('title', 'Import')
@section('header', 'Business Import')

@section('content')
<div class="mb-6">
    <p class="text-slate-400">Import businesses from Google Maps, AI scraping, or CSV files.</p>
</div>

<!-- Import Tabs -->
<div class="flex gap-2 mb-6">
    <button onclick="showTab('google')" id="tab-google" class="px-4 py-2 rounded-lg bg-cyan-500/20 text-cyan-400 text-sm font-medium transition-all">
        Google Maps
    </button>
    <button onclick="showTab('serpapi')" id="tab-serpapi" class="px-4 py-2 rounded-lg bg-slate-700/50 text-slate-400 text-sm font-medium transition-all">
        SerpAPI
    </button>
    <button onclick="showTab('ai')" id="tab-ai" class="px-4 py-2 rounded-lg bg-slate-700/50 text-slate-400 text-sm font-medium transition-all">
        AI Scraper
    </button>
    <button onclick="showTab('csv')" id="tab-csv" class="px-4 py-2 rounded-lg bg-slate-700/50 text-slate-400 text-sm font-medium transition-all">
        CSV Upload
    </button>
    <a href="{{ route('admin.import.review') }}" class="px-4 py-2 rounded-lg bg-emerald-500/20 text-emerald-400 text-sm font-medium">
        Review Queue ({{ \App\Models\ImportItem::pending()->count() }})
    </a>
</div>

<!-- Google Places -->
<div id="panel-google" class="glass-card p-6 rounded-xl">
    <h3 class="font-semibold text-white mb-3">Import from Google Maps</h3>
    <p class="text-sm text-slate-400 mb-4">Enter an area name or zipcode — we'll find businesses near that location.</p>

    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Search Query</label>
            <input type="text" id="google-query" class="input-dark" placeholder="e.g., restaurants, schools">
        </div>
        <div>
            <label class="block text-sm text-slate-400 mb-1">Agent</label>
            <select id="google-agent" class="input-dark">
                @foreach(\App\Models\AiAgent::whereJsonContains('skills', 'google_places_import')->get() as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->avatar }} {{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mb-4">
        <label class="block text-sm text-slate-400 mb-1">Area / Zipcode</label>
        <input type="text" id="google-zipcode" class="input-dark" value="Churachandpur, Manipur" placeholder="e.g., 795128 or Lamka, Churachandpur">
        <p class="text-slate-500 text-xs mt-1">We'll automatically find the coordinates for this location</p>
    </div>
    <div class="grid grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Radius (meters)</label>
            <input type="number" id="google-radius" class="input-dark" value="10000">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Max Results</label>
            <input type="number" id="google-max" class="input-dark" value="20" min="1" max="60">
        </div>
    </div>
    <details class="mb-4">
        <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-400">Advanced: Manual coordinates</summary>
        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Latitude</label>
                <input type="text" id="google-lat" class="input-dark">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Longitude</label>
                <input type="text" id="google-lng" class="input-dark">
            </div>
        </div>
    </details>
    <button onclick="runGoogleImport()" class="btn-primary">Search & Import</button>
</div>

<!-- SerpAPI -->
<div id="panel-serpapi" class="glass-card p-6 rounded-xl hidden">
    <h3 class="font-semibold text-white mb-3">Search via SerpAPI</h3>
    <p class="text-sm text-slate-400 mb-4">Scrapes real Google search results for businesses in your area.</p>

    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Search Query</label>
            <input type="text" id="serpapi-query" class="input-dark" placeholder="e.g., restaurants, hotels, shops">
        </div>
        <div>
            <label class="block text-sm text-slate-400 mb-1">Agent</label>
            <select id="serpapi-agent" class="input-dark">
                @foreach(\App\Models\AiAgent::whereJsonContains('skills', 'serpapi_business_search')->get() as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->avatar }} {{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Area / Zipcode</label>
            <input type="text" id="serpapi-area" class="input-dark" value="Churachandpur, Manipur" placeholder="e.g., 795128 or Lamka, Churachandpur">
        </div>
        <div>
            <label class="block text-sm text-slate-400 mb-1">Max Results</label>
            <input type="number" id="serpapi-max" class="input-dark" value="20" min="1" max="50">
        </div>
    </div>
    <button onclick="runSerpapiImport()" class="btn-primary">Search via SerpAPI</button>
</div>

<!-- AI Scraper -->
<div id="panel-ai" class="glass-card p-6 rounded-xl hidden">
    <h3 class="font-semibold text-white mb-3">AI Business Discovery</h3>
    <p class="text-sm text-slate-400 mb-4">Uses AI to discover businesses in your area. Results need review.</p>

    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Search Query / Category</label>
            <input type="text" id="ai-category" class="input-dark" placeholder="e.g., restaurants, all businesses">
        </div>
        <div>
            <label class="block text-sm text-slate-400 mb-1">Agent</label>
            <select id="ai-agent" class="input-dark">
                @foreach(\App\Models\AiAgent::whereJsonContains('skills', 'ai_business_scraper')->get() as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->avatar }} {{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Area / Zipcode</label>
            <input type="text" id="ai-area" class="input-dark" value="Churachandpur, Manipur" placeholder="e.g., 795128 or Lamka, Churachandpur">
        </div>
        <div>
            <label class="block text-sm text-slate-400 mb-1">Max Results</label>
            <input type="number" id="ai-max" class="input-dark" value="30" min="1" max="50">
        </div>
    </div>
    <button onclick="runAiScrape()" class="btn-primary">Run AI Discovery</button>
</div>

<!-- CSV Upload -->
<div id="panel-csv" class="glass-card p-6 rounded-xl hidden">
    <h3 class="font-semibold text-white mb-3">Bulk Import from CSV</h3>
    <p class="text-sm text-slate-400 mb-4">Upload a CSV file with columns: name, category, address, phone, email, website, description</p>

    <form id="csv-form" action="{{ route('admin.import.csv') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-4">
            <input type="file" name="csv_file" accept=".csv" class="input-dark" required>
        </div>
        <button type="submit" class="btn-primary">Upload & Import</button>
    </form>
</div>

<!-- Recent Batches -->
<div class="glass-card p-6 rounded-xl mt-6">
    <h3 class="font-semibold text-white mb-3">Import History</h3>
    @if($batches->isEmpty())
        <p class="text-slate-500 text-sm">No imports yet.</p>
    @else
        <div class="space-y-2">
            @foreach($batches as $batch)
                <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-800/50">
                    <span class="text-lg">
                        @if($batch['status'] === 'completed') ✅
                        @elseif($batch['status'] === 'processing') ⏳
                        @else ⏸️
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-white">{{ $batch['name'] ?? $batch['source'] }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $batch['total'] }} total, {{ $batch['approved'] }} approved, {{ $batch['rejected'] }} rejected
                            @if($batch['agent'] ?? null) · {{ $batch['agent']['name'] }} @endif
                        </p>
                    </div>
                    <span class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($batch['created_at'])->diffForHumans() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('[id^="panel-"]').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('[id^="tab-"]').forEach(el => {
        el.classList.remove('bg-cyan-500/20', 'text-cyan-400');
        el.classList.add('bg-slate-700/50', 'text-slate-400');
    });
    document.getElementById('panel-' + tab).classList.remove('hidden');
    document.getElementById('tab-' + tab).classList.remove('bg-slate-700/50', 'text-slate-400');
    document.getElementById('tab-' + tab).classList.add('bg-cyan-500/20', 'text-cyan-400');
}

function runGoogleImport() {
    const agentId = document.getElementById('google-agent').value;
    if (!agentId) { alert('Select an agent first'); return; }

    const lat = document.getElementById('google-lat').value;
    const lng = document.getElementById('google-lng').value;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/agents/${agentId}/run`;
    form.innerHTML = `
        @csrf
        <input type="hidden" name="skill" value="google_places_import">
        <input type="hidden" name="query" value="${document.getElementById('google-query').value}">
        <input type="hidden" name="area" value="${document.getElementById('google-zipcode').value}">
        <input type="hidden" name="radius" value="${document.getElementById('google-radius').value}">
        <input type="hidden" name="max_results" value="${document.getElementById('google-max').value}">
        ${lat ? `<input type="hidden" name="latitude" value="${lat}">` : ''}
        ${lng ? `<input type="hidden" name="longitude" value="${lng}">` : ''}
    `;
    document.body.appendChild(form);
    form.submit();
}

function runAiScrape() {
    const agentId = document.getElementById('ai-agent').value;
    if (!agentId) { alert('Select an agent first'); return; }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/agents/${agentId}/run`;
    form.innerHTML = `
        @csrf
        <input type="hidden" name="skill" value="ai_business_scraper">
        <input type="hidden" name="area" value="${document.getElementById('ai-area').value}">
        <input type="hidden" name="category" value="${document.getElementById('ai-category').value}">
        <input type="hidden" name="max_results" value="${document.getElementById('ai-max').value}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function runSerpapiImport() {
    const agentId = document.getElementById('serpapi-agent').value;
    if (!agentId) { alert('Select an agent first'); return; }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/admin/agents/${agentId}/run`;
    form.innerHTML = `
        @csrf
        <input type="hidden" name="skill" value="serpapi_business_search">
        <input type="hidden" name="query" value="${document.getElementById('serpapi-query').value}">
        <input type="hidden" name="area" value="${document.getElementById('serpapi-area').value}">
        <input type="hidden" name="max_results" value="${document.getElementById('serpapi-max').value}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection
