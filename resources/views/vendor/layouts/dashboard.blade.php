<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Vendor Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        dark: { 50:'#f8fafc',100:'#e2e8f0',200:'#cbd5e1',300:'#94a3b8',400:'#64748b',500:'#475569',600:'#334155',700:'#1e293b',800:'#0f172a',900:'#020617' }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }

        body { background: #06080f; color: #e2e8f0; }

        .sidebar {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-link {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; border-radius: 12px;
            color: #94a3b8; font-size: 14px; font-weight: 500;
            transition: all 0.2s ease; position: relative; overflow: hidden;
        }

        .sidebar-link:hover { background: rgba(255,255,255,0.05); color: #e2e8f0; }

        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(139,92,246,0.15) 0%, rgba(168,85,247,0.15) 100%);
            color: #fff;
            box-shadow: 0 0 20px rgba(139,92,246,0.1);
        }

        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 24px; background: linear-gradient(180deg, #8b5cf6, #a855f7);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-link svg { width: 20px; height: 20px; flex-shrink: 0; }

        .main-bg {
            background: radial-gradient(ellipse at 20% 0%, rgba(139,92,246,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 100%, rgba(168,85,247,0.06) 0%, transparent 50%);
        }

        .glass-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(255,255,255,0.1);
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .stat-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 24px;
            position: relative; overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-2px); border-color: rgba(255,255,255,0.1); }

        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }

        .top-header {
            background: rgba(6, 8, 15, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .data-table th {
            padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600;
            color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .data-table td {
            padding: 14px 16px; font-size: 14px; color: #cbd5e1;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        .input-dark {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; padding: 10px 14px; color: #e2e8f0; font-size: 14px;
            transition: all 0.2s ease; width: 100%;
        }
        .input-dark:focus {
            outline: none; border-color: rgba(139,92,246,0.5);
            box-shadow: 0 0 16px rgba(139,92,246,0.1);
        }
        .input-dark::placeholder { color: #475569; }

        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #a855f7 100%);
            color: white; padding: 10px 24px; border-radius: 10px; font-weight: 600; font-size: 14px;
            border: none; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(139,92,246,0.3); }
        .btn-primary:active { transform: scale(0.98); }

        .btn-ghost {
            background: rgba(255,255,255,0.05); color: #94a3b8; padding: 10px 24px;
            border-radius: 10px; font-weight: 500; font-size: 14px;
            border: 1px solid rgba(255,255,255,0.08); cursor: pointer; transition: all 0.2s ease;
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.08); color: #e2e8f0; }

        .btn-danger {
            background: rgba(239,68,68,0.1); color: #ef4444; padding: 10px 24px;
            border-radius: 10px; font-weight: 500; font-size: 14px;
            border: 1px solid rgba(239,68,68,0.2); cursor: pointer; transition: all 0.2s ease;
        }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }

        .badge {
            display: inline-flex; align-items: center; padding: 4px 10px;
            border-radius: 6px; font-size: 12px; font-weight: 600;
        }
        .badge-green { background: rgba(34,197,94,0.1); color: #22c55e; }
        .badge-yellow { background: rgba(234,179,8,0.1); color: #eab308; }
        .badge-red { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-blue { background: rgba(139,92,246,0.1); color: #8b5cf6; }

        .toast {
            position: fixed; top: 24px; right: 24px; z-index: 100;
            padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 500;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 3s forwards;
        }
        .toast-success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .toast-error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }

        @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(-10px); } }

        .mobile-menu { display: none; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 50; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .mobile-menu { display: flex; }
            .main-content { margin-left: 0 !important; }
        }

        .orb {
            position: fixed; border-radius: 50%; filter: blur(100px); z-index: 0; pointer-events: none;
        }
        .orb-1 { width: 400px; height: 400px; background: rgba(139,92,246,0.05); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: rgba(168,85,247,0.04); bottom: -50px; left: -50px; }
    </style>
    @stack('styles')
</head>
<body class="overflow-hidden">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="flex h-screen relative z-10">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col mobile-menu" id="sidebar">
            <!-- Logo -->
            <div class="p-6 border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center">
                        <span class="text-white font-bold text-lg">V</span>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-lg leading-tight">Vendor</h1>
                        <p class="text-slate-500 text-xs">Vendor Panel</p>
                    </div>
                </div>
            </div>

            @php
                $allBizs = \App\Models\Business::where('created_by', Auth::id())->get();
                $currentBizId = request()->route('businessId');
                if (!$currentBizId) {
                    $currentBizId = $allBizs->first()->id ?? null;
                }
                $currentBiz = $currentBizId ? $allBizs->firstWhere('id', $currentBizId) : null;
                if (!$currentBiz && $allBizs->isNotEmpty()) {
                    $currentBiz = $allBizs->first();
                    $currentBizId = $currentBiz->id;
                }
                $mods = $currentBiz ? (array) $currentBiz->enabled_modules : [];
            @endphp

            <!-- Nav -->
            <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
                @if($allBizs->count() > 1)
                <div class="px-2 mb-3">
                    <select onchange="if(this.value) window.location.href=this.value" class="input-dark text-sm w-full">
                        @foreach($allBizs as $b)
                            <option value="{{ route('vendor.products', $b->id) }}" {{ $b->id === $currentBizId ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider px-4 mb-2 mt-2">Main</p>
                <a href="{{ route('vendor.dashboard') }}" class="sidebar-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider px-4 mb-2 mt-6">Business</p>
                <a href="{{ route('vendor.businesses') }}" class="sidebar-link {{ request()->routeIs('vendor.businesses*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    My Business
                </a>

                @if($currentBizId)
                @if(($mods['orders'] ?? false) || ($mods['bookings'] ?? false))
                <p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider px-4 mb-2 mt-6">Catalog</p>
                @if($mods['orders'] ?? false)
                <a href="{{ route('vendor.products', $currentBizId) }}" class="sidebar-link {{ request()->routeIs('vendor.products*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Products
                </a>
                @endif
                @if($mods['bookings'] ?? false)
                <a href="{{ route('vendor.services', $currentBizId) }}" class="sidebar-link {{ request()->routeIs('vendor.services*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Services
                </a>
                @endif
                @endif

                @if(($mods['orders'] ?? false) || ($mods['bookings'] ?? false))
                <p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider px-4 mb-2 mt-6">Orders & Bookings</p>
                @if($mods['orders'] ?? false)
                <a href="{{ route('vendor.orders', $currentBizId) }}" class="sidebar-link {{ request()->routeIs('vendor.orders*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Orders
                </a>
                @endif
                @if($mods['bookings'] ?? false)
                <a href="{{ route('vendor.bookings', $currentBizId) }}" class="sidebar-link {{ request()->routeIs('vendor.bookings*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Bookings
                </a>
                @endif
                @endif
                @endif

                <p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wider px-4 mb-2 mt-6">Account</p>
                <a href="{{ route('vendor.settings') }}" class="sidebar-link {{ request()->routeIs('vendor.settings*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Settings
                </a>
            </nav>

            <!-- User -->
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white text-sm font-bold">
                        {{ substr(Auth::user()->name ?? 'V', 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ Auth::user()->name ?? 'Vendor' }}</p>
                        <p class="text-slate-500 text-xs truncate">{{ Auth::user()->email ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('vendor.logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="sidebar-link w-full text-red-400 hover:text-red-300 hover:bg-red-500/10">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Overlay for mobile -->
        <div class="fixed inset-0 bg-black/50 z-40 hidden" id="overlay" onclick="toggleSidebar()"></div>

        <!-- Main -->
        <div class="flex-1 flex flex-col main-content main-bg overflow-hidden" style="margin-left: 0;">
            <!-- Header -->
            <header class="top-header px-6 py-4 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="mobile-menu p-2 rounded-lg hover:bg-white/5 text-slate-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h2 class="text-white font-semibold text-lg">@yield('header', 'Dashboard')</h2>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ url('/') }}" target="_blank" class="p-2 rounded-lg hover:bg-white/5 text-slate-400 transition" title="View Site">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
            </header>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6" id="content">
                @if(session('success'))
                    <div class="toast toast-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="toast toast-error">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('hidden');
        }

        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', e => {
                if (!confirm(form.dataset.confirm)) e.preventDefault();
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
