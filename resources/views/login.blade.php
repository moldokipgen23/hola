<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vendor Login - Hola</title>
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
        body {
            background: radial-gradient(ellipse at center top, rgba(139,92,246,0.08) 0%, transparent 60%),
                        radial-gradient(ellipse at center bottom, rgba(168,85,247,0.06) 0%, transparent 60%),
                        #06080f;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .orb {
            position: fixed; border-radius: 50%; filter: blur(100px); z-index: 0; pointer-events: none;
        }
        .orb-1 { width: 400px; height: 400px; background: rgba(139,92,246,0.05); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: rgba(168,85,247,0.04); bottom: -50px; left: -50px; }
        .glass-card {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
        }
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
            border: none; cursor: pointer; transition: all 0.3s ease; width: 100%;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(139,92,246,0.3); }
        .btn-primary:active { transform: scale(0.98); }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="relative z-10 w-full max-w-md px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500 to-purple-700 mb-4">
                <span class="text-white font-bold text-2xl">V</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Vendor Dashboard</h1>
            <p class="text-slate-400 text-sm mt-1">Sign in to manage your business</p>
        </div>

        <div class="glass-card p-8">
            <div class="h-1.5 w-full rounded-full bg-gradient-to-r from-purple-500 to-purple-700 -mt-8 mb-6"></div>

            @if($errors->any())
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('vendor.login') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-400 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="input-dark" placeholder="vendor@example.com">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-400 mb-1.5">Password</label>
                    <input type="password" name="password" required
                        class="input-dark" placeholder="Enter your password">
                </div>

                <button type="submit" class="btn-primary">
                    Login to Vendor Dashboard
                </button>
            </form>
        </div>

        <div class="text-center mt-6">
            <a href="{{ url('/') }}" class="text-slate-500 text-sm hover:text-slate-300 transition">
                &larr; Back to main site
            </a>
        </div>
    </div>
</body>
</html>
