<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Eiho One</title>
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

        /* ─── Sidebar ─── */
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
            background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(168,85,247,0.15) 100%);
            color: #fff;
            box-shadow: 0 0 20px rgba(59,130,246,0.1);
        }

        .sidebar-link.active::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 24px; background: linear-gradient(180deg, #3b82f6, #a855f7);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-link svg { width: 20px; height: 20px; flex-shrink: 0; }

        /* ─── Accordion Sidebar ─── */
        .sidebar-group-toggle {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; padding: 8px 12px; border-radius: 10px;
            color: #64748b; cursor: pointer;
            font-size: 11px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
            transition: background 0.2s ease, color 0.2s ease;
            margin-top: 18px;
        }

        .sidebar-group-toggle:hover { background: rgba(255,255,255,0.04); color: #94a3b8; }

        .sidebar-group-toggle .chevron { width: 14px; height: 14px; transition: transform 0.25s ease; }

        .sidebar-group-toggle.collapsed .chevron { transform: rotate(-90deg); }

        .accordion-body {
            display: grid; grid-template-rows: 1fr;
            transition: grid-template-rows 0.25s ease;
        }

        .accordion-body.collapsed { grid-template-rows: 0fr; }

        .accordion-body > div { overflow: hidden; min-height: 0; }

        .accordion-inner { padding-top: 4px; }

        /* ─── Main ─── */
        .main-bg {
            background: radial-gradient(ellipse at 20% 0%, rgba(59,130,246,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 100%, rgba(168,85,247,0.06) 0%, transparent 50%);
        }

        /* ─── Cards ─── */
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

        /* ─── Stat Card ─── */
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

        /* ─── Header ─── */
        .top-header {
            background: rgba(6, 8, 15, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        /* ─── Table ─── */
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

        /* ─── Inputs ─── */
        .input-dark {
            background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px; padding: 10px 14px; color: #e2e8f0; font-size: 14px;
            transition: all 0.2s ease; width: 100%;
        }
        .input-dark:focus {
            outline: none; border-color: rgba(59,130,246,0.5);
            box-shadow: 0 0 16px rgba(59,130,246,0.1);
        }
        .input-dark::placeholder { color: #475569; }

        /* ─── Buttons ─── */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white; padding: 10px 24px; border-radius: 10px; font-weight: 600; font-size: 14px;
            border: none; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(59,130,246,0.3); }
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

        /* ─── Badge ─── */
        .badge {
            display: inline-flex; align-items: center; padding: 4px 10px;
            border-radius: 6px; font-size: 12px; font-weight: 600;
        }
        .badge-green { background: rgba(34,197,94,0.1); color: #22c55e; }
        .badge-yellow { background: rgba(234,179,8,0.1); color: #eab308; }
        .badge-red { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; }

        /* ─── Sidebar badge ─── */
        .sidebar-badge {
            margin-left: auto;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            min-width: 20px;
            border-radius: 9999px;
            text-align: center;
            line-height: 1.4;
            flex-shrink: 0;
        }

        /* ─── Toast ─── */
        .toast {
            position: fixed; top: 24px; right: 24px; z-index: 100;
            padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 500;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 3s forwards;
        }
        .toast-success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .toast-error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }

        @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(-10px); } }

        /* ─── Mobile ─── */
        .mobile-menu { display: none; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 50; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .mobile-menu { display: flex; }
            .main-content { margin-left: 0 !important; }
        }

        /* ─── Orb ─── */
        .orb {
            position: fixed; border-radius: 50%; filter: blur(100px); z-index: 0; pointer-events: none;
        }
        .orb-1 { width: 400px; height: 400px; background: rgba(59,130,246,0.05); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: rgba(168,85,247,0.04); bottom: -50px; left: -50px; }
    </style>
</head>
<body class="overflow-hidden">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="flex h-screen relative z-10">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col" id="sidebar">
            <!-- Logo -->
            <div class="p-6 border-b border-white/5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                        <span class="text-white font-bold text-lg">H</span>
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-lg leading-tight">Eiho One</h1>
                        <p class="text-slate-500 text-xs">Admin Panel</p>
                    </div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 p-4 overflow-y-auto">
                <div>
                    @foreach ($menuGroups as $group)
                        @if (isset($group['feature']) && ! $launchControl->enabled($group['feature']))
                            @continue
                        @endif

                        @php
                            $groupHasActive = collect($group['items'])
                                ->contains(fn ($item) => request()->routeIs($item['route']));
                        @endphp

                        <div class="sidebar-group" data-group="{{ $group['title'] }}">
                            <button type="button" class="sidebar-group-toggle {{ $groupHasActive ? '' : 'collapsed' }}" onclick="toggleSidebarGroup(this)">
                                <span>{{ $group['title'] }}</span>
                                <svg class="chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div class="accordion-body {{ $groupHasActive ? '' : 'collapsed' }}">
                                <div>
                                    <div class="accordion-inner space-y-1">
                                        @foreach ($group['items'] as $item)
                                            @if (isset($item['feature']) && ! $launchControl->enabled($item['feature']))
                                                @continue
                                            @endif

                                            <a href="{{ $item['url'] ?? route(str_replace('*', '', $item['route'])) }}" class="sidebar-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                                <x-admin.icon :name="$item['icon']" class="w-5 h-5" />
                                                <span>{{ $item['label'] }}</span>
                                                @if (($item['badge'] ?? 0) > 0)
                                                    <span class="sidebar-badge">{{ $item['badge'] }}{{ $item['badge_suffix'] ?? '' }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </nav>

            <!-- User -->
            <div class="p-4 border-t border-white/5">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold">
                        {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ Auth::user()->name ?? 'Admin' }}</p>
                        <p class="text-slate-500 text-xs truncate">{{ Auth::user()->email ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
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
        <div class="flex-1 flex flex-col main-content main-bg overflow-hidden">
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

        function setSidebarGroup(group, open) {
            const body = group.querySelector('.accordion-body');
            const toggle = group.querySelector('.sidebar-group-toggle');
            body.classList.toggle('collapsed', !open);
            toggle.classList.toggle('collapsed', !open);
        }

        function initSidebarAccordion() {
            document.querySelectorAll('.sidebar-group').forEach(group => {
                const hasActive = !!group.querySelector('.sidebar-link.active');
                if (hasActive) { setSidebarGroup(group, true); return; }
                const stored = localStorage.getItem('admin-sidebar:' + group.dataset.group);
                setSidebarGroup(group, stored !== '0');
            });
        }

        function toggleSidebarGroup(toggle) {
            const group = toggle.closest('.sidebar-group');
            const isCollapsed = group.querySelector('.accordion-body').classList.contains('collapsed');
            setSidebarGroup(group, isCollapsed);
            localStorage.setItem('admin-sidebar:' + group.dataset.group, isCollapsed ? '1' : '0');
        }

        initSidebarAccordion();

        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', e => {
                if (!confirm(form.dataset.confirm)) e.preventDefault();
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
