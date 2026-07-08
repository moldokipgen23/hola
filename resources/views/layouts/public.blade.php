<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'Hola'))</title>
    <meta name="description" content="@yield('description', 'Discover local businesses in Lamka, Churachandpur, Manipur, India')">

    <!-- Open Graph -->
    <meta property="og:title" content="@yield('og_title', config('app.name', 'Hola'))">
    <meta property="og:description" content="@yield('og_description', 'Discover local businesses in Lamka, Churachandpur, Manipur, India')">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', config('app.name', 'Hola'))">
    <meta name="twitter:description" content="@yield('og_description', 'Discover local businesses in Lamka, Churachandpur, Manipur, India')">
    <meta name="twitter:image" content="@yield('og_image', asset('images/og-default.png'))">

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
    </style>
    @yield('head')
</head>
<body>
    <!-- Header -->
    <header class="border-b border-white/5">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm">H</div>
                <span class="text-white font-bold text-lg">Hola</span>
            </a>
            <nav class="flex items-center gap-6 text-sm text-slate-400">
                <a href="/" class="hover:text-white transition">Home</a>
                <a href="/businesses" class="hover:text-white transition">Businesses</a>
                <a href="/categories" class="hover:text-white transition">Categories</a>
            </nav>
        </div>
    </header>

    <!-- Content -->
    <main class="max-w-6xl mx-auto px-4 py-8">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-white/5 mt-12">
        <div class="max-w-6xl mx-auto px-4 py-8 text-center text-slate-500 text-sm">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Hola') }}. All rights reserved.</p>
            <p class="mt-1">Discover local businesses in Lamka, Churachandpur, Manipur, India</p>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
