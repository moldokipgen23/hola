<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hola - Churachandpur Business Directory')</title>
    <meta name="description" content="@yield('description', 'Discover local businesses in Lamka, Churachandpur, Manipur. Find restaurants, shops, services, and more.')">
    <meta name="keywords" content="Churachandpur, Lamka, business directory, Manipur, local businesses, shops, restaurants">

    {{-- Open Graph --}}
    <meta property="og:title" content="@yield('og_title', 'Hola - Churachandpur Business Directory')">
    <meta property="og:description" content="@yield('og_description', 'Discover local businesses in Lamka, Churachandpur, Manipur.')">
    <meta property="og:image" content="@yield('og_image', '')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Hola - Churachandpur Business Directory')">
    <meta name="twitter:description" content="@yield('og_description', 'Discover local businesses in Lamka, Churachandpur, Manipur.')">

    {{-- Tailwind CSS CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8' },
                        accent: { 500:'#8b5cf6',600:'#7c3aed' },
                    }
                }
            }
        }
    </script>

    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'><stop offset='0%25' stop-color='%233b82f6'/><stop offset='100%25' stop-color='%238b5cf6'/></linearGradient></defs><rect width='100' height='100' rx='20' fill='url(%23g)'/><text x='50' y='68' font-family='Arial,sans-serif' font-size='55' font-weight='bold' fill='white' text-anchor='middle'>H</text></svg>">

    @yield('head')

    <style>
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; }

        .search-glow:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }

        .category-card { transition: all 0.2s ease; }
        .category-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }

        .business-card { transition: all 0.2s ease; }
        .business-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }

        .area-card { transition: all 0.2s ease; }
        .area-card:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,0,0,0.08); }

        .search-dropdown { max-height: 400px; overflow-y: auto; }

        .hero-gradient { background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 50%, #f5f3ff 100%); }

        .stat-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s ease forwards; }

        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    {{-- HEADER --}}
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                {{-- Logo --}}
                <a href="/" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-sm shadow-md group-hover:shadow-lg transition-shadow">
                        H
                    </div>
                    <span class="text-lg font-bold text-slate-900">Hola</span>
                </a>

                {{-- Desktop Nav --}}
                <nav class="hidden md:flex items-center gap-1">
                    <a href="/" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('/') ? 'text-primary-600 bg-primary-50' : '' }}">Home</a>
                    <a href="/businesses" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('businesses*') ? 'text-primary-600 bg-primary-50' : '' }}">Businesses</a>
                    <a href="/areas" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('area*') ? 'text-primary-600 bg-primary-50' : '' }}">Areas</a>
                    <a href="/categories" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('categor*') ? 'text-primary-600 bg-primary-50' : '' }}">Categories</a>
                    <a href="/explore" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('explore') ? 'text-primary-600 bg-primary-50' : '' }}">Explore</a>
                    <a href="/map" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-primary-600 hover:bg-primary-50 transition-colors {{ request()->is('map') ? 'text-primary-600 bg-primary-50' : '' }}">Map</a>
                </nav>

                {{-- Mobile Menu Button --}}
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div id="mobileMenu" class="hidden md:hidden border-t border-slate-100 bg-white">
            <div class="px-4 py-3 space-y-1">
                <a href="/" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Home</a>
                <a href="/businesses" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Businesses</a>
                <a href="/areas" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Areas</a>
                <a href="/categories" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Categories</a>
                <a href="/explore" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Explore</a>
                <a href="/map" class="block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50">Map</a>
            </div>
        </div>
    </header>

    {{-- MAIN CONTENT --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="bg-white border-t border-slate-200 mt-12">
        <div class="max-w-6xl mx-auto px-4 py-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                {{-- Brand --}}
                <div class="md:col-span-1">
                    <a href="/" class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-xs">H</div>
                        <span class="text-base font-bold text-slate-900">Hola</span>
                    </a>
                    <p class="text-sm text-slate-500">Discover local businesses in Lamka, Churachandpur, Manipur, India.</p>
                </div>

                {{-- Quick Links --}}
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 mb-3">Explore</h4>
                    <ul class="space-y-2">
                        <li><a href="/businesses" class="text-sm text-slate-500 hover:text-primary-600">All Businesses</a></li>
                        <li><a href="/categories" class="text-sm text-slate-500 hover:text-primary-600">Categories</a></li>
                        <li><a href="/areas" class="text-sm text-slate-500 hover:text-primary-600">Areas</a></li>
                        <li><a href="/map" class="text-sm text-slate-500 hover:text-primary-600">Map View</a></li>
                    </ul>
                </div>

                {{-- Areas --}}
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 mb-3">Popular Areas</h4>
                    <ul class="space-y-2">
                        <li><a href="/area/lamka" class="text-sm text-slate-500 hover:text-primary-600">Lamka</a></li>
                        <li><a href="/area/tuibong" class="text-sm text-slate-500 hover:text-primary-600">Tuibong</a></li>
                        <li><a href="/area/new-lamka" class="text-sm text-slate-500 hover:text-primary-600">New Lamka</a></li>
                        <li><a href="/area/hiangtam-lamka" class="text-sm text-slate-500 hover:text-primary-600">Hiangtam Lamka</a></li>
                    </ul>
                </div>

                {{-- About --}}
                <div>
                    <h4 class="text-sm font-semibold text-slate-900 mb-3">About</h4>
                    <ul class="space-y-2">
                        <li><span class="text-sm text-slate-500">Churachandpur Business Directory</span></li>
                        <li><span class="text-sm text-slate-500">Manipur, India</span></li>
                        <li><a href="/admin" class="text-sm text-slate-500 hover:text-primary-600">Admin Login</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-100 mt-8 pt-6 flex flex-col md:flex-row items-center justify-between gap-3">
                <p class="text-xs text-slate-400">&copy; {{ date('Y') }} Hola. All rights reserved.</p>
                <p class="text-xs text-slate-400">Made with care for Churachandpur</p>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        }
    </script>

    {{-- Back to Top --}}
    <button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" class="fixed bottom-6 right-6 z-50 w-10 h-10 rounded-full bg-primary-500 text-white shadow-lg hover:bg-primary-600 transition-all hidden">
        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
    </button>

    @yield('scripts')
    <script>
        window.addEventListener('scroll', () => {
            document.getElementById('backToTop').classList.toggle('hidden', window.scrollY < 300);
        });
    </script>
</body>
</html>
