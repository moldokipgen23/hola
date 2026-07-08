<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hola Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: #0a0a0f;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ─── Animated Background ─── */
        .bg-animated {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: radial-gradient(ellipse at 20% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
                        radial-gradient(ellipse at 40% 80%, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
            animation: bgPulse 8s ease-in-out infinite;
        }

        @keyframes bgPulse {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; }
        }

        /* ─── Floating Particles ─── */
        .particles {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float linear infinite;
        }

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) rotate(720deg); opacity: 0; }
        }

        /* ─── Grid Lines ─── */
        .grid-lines {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: perspective(500px) rotateX(0deg); }
            100% { transform: perspective(500px) rotateX(360deg); }
        }

        /* ─── 3D Card ─── */
        .card-3d {
            position: relative;
            z-index: 10;
            perspective: 1000px;
        }

        .card-inner {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            transform-style: preserve-3d;
            transition: transform 0.1s ease-out, box-shadow 0.3s ease;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5),
                        0 0 100px rgba(59, 130, 246, 0.05),
                        inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .card-inner:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6),
                        0 0 120px rgba(59, 130, 246, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        /* ─── Glow Logo ─── */
        .logo-glow {
            position: relative;
            display: inline-block;
        }

        .logo-glow::before {
            content: '';
            position: absolute;
            inset: -20px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(20px);
            animation: logoPulse 3s ease-in-out infinite;
        }

        @keyframes logoPulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.1); }
        }

        /* ─── Input Focus Glow ─── */
        .input-glow {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .input-glow:focus {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15),
                        0 0 40px rgba(59, 130, 246, 0.05);
            outline: none;
        }

        /* ─── Button ─── */
        .btn-3d {
            position: relative;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #ec4899 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .btn-3d::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-3d:hover::before {
            opacity: 1;
        }

        .btn-3d:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(59, 130, 246, 0.4);
        }

        .btn-3d:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 5px 20px rgba(59, 130, 246, 0.3);
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ─── Orb ─── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            animation: orbFloat 10s ease-in-out infinite;
            z-index: 0;
        }

        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(59, 130, 246, 0.08);
            top: -100px; left: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 300px; height: 300px;
            background: rgba(168, 85, 247, 0.08);
            bottom: -50px; right: -50px;
            animation-delay: -3s;
        }

        .orb-3 {
            width: 250px; height: 250px;
            background: rgba(236, 72, 153, 0.06);
            top: 50%; left: 60%;
            animation-delay: -6s;
        }

        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }

        /* ─── Error ─── */
        .error-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-10px); }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }

        /* ─── Loading ─── */
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px; height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            left: 50%; top: 50%;
            margin-left: -10px; margin-top: -10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ─── Responsive ─── */
        @media (max-width: 480px) {
            .card-inner {
                padding: 36px 24px;
                margin: 16px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="bg-animated"></div>
    <div class="grid-lines"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Particles -->
    <div class="particles" id="particles"></div>

    <!-- Card -->
    <div class="card-3d px-4 w-full flex justify-center">
        <div class="card-inner" id="card">

            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="logo-glow inline-block mb-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 flex items-center justify-center mx-auto">
                        <span class="text-3xl font-bold text-white">H</span>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-white mb-1">Hola Admin</h1>
                <p class="text-sm text-white/40">Lamka Directory Management</p>
            </div>

            <!-- Error -->
            @if($errors->any())
                <div class="error-shake bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-6 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('admin.login.post') }}" id="loginForm">
                @csrf

                <!-- Email -->
                <div class="mb-5">
                    <label class="block text-xs font-medium text-white/50 mb-2 uppercase tracking-wider">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="input-glow w-full px-4 py-3.5 rounded-xl text-white text-sm placeholder-white/20"
                        placeholder="admin@hola.app"
                        autocomplete="email">
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-xs font-medium text-white/50 mb-2 uppercase tracking-wider">Password</label>
                    <input type="password" name="password" required
                        class="input-glow w-full px-4 py-3.5 rounded-xl text-white text-sm placeholder-white/20"
                        placeholder="Enter password"
                        autocomplete="current-password">
                </div>

                <!-- Button -->
                <button type="submit" id="btn"
                    class="btn-3d w-full py-3.5 rounded-xl text-white font-semibold text-sm tracking-wide">
                    Sign In
                </button>
            </form>

            <!-- Demo -->
            <div class="mt-6 pt-5 border-t border-white/5 text-center">
                <p class="text-xs text-white/30">Demo: <span class="text-white/50">admin@hola.app</span> / <span class="text-white/50">password</span></p>
            </div>
        </div>
    </div>

    <script>
        // ─── Particles ───
        const particlesEl = document.getElementById('particles');
        for (let i = 0; i < 50; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.width = p.style.height = (Math.random() * 4 + 1) + 'px';
            p.style.animationDuration = (Math.random() * 15 + 10) + 's';
            p.style.animationDelay = (Math.random() * 10) + 's';
            particlesEl.appendChild(p);
        }

        // ─── 3D Tilt on Touch/Mouse ───
        const card = document.getElementById('card');
        let ticking = false;

        function handleMove(x, y) {
            if (ticking) return;
            ticking = true;

            requestAnimationFrame(() => {
                const rect = card.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const rotateY = ((x - centerX) / (rect.width / 2)) * 8;
                const rotateX = ((centerY - y) / (rect.height / 2)) * 8;
                card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
                ticking = false;
            });
        }

        function handleEnd() {
            card.style.transform = 'rotateX(0) rotateY(0) scale3d(1, 1, 1)';
        }

        document.addEventListener('mousemove', e => handleMove(e.clientX, e.clientY));
        document.addEventListener('touchmove', e => {
            e.preventDefault();
            handleMove(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: false });

        document.addEventListener('mouseup', handleEnd);
        document.addEventListener('touchend', handleEnd);

        // ─── Button Loading ───
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btn');
            btn.classList.add('btn-loading');
            btn.textContent = '';
        });
    </script>
</body>
</html>
