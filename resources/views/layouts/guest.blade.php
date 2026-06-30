<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $branding->name }}</title>

        <link rel="icon" href="{{ $branding->faviconUrl }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root { --brand: {{ $branding->primary }}; --accent: {{ $branding->accent }}; }

            .auth-shell {
                min-height:100vh; display:flex; flex-direction:column;
                align-items:center; justify-content:center; padding:24px;
                background:
                    radial-gradient(1100px 540px at 50% -10%, color-mix(in srgb, var(--brand, #F58220) 14%, transparent), transparent),
                    #f3f4f6;
                font-family:'Figtree', ui-sans-serif, system-ui, sans-serif;
            }
            .auth-brand { display:flex; flex-direction:column; align-items:center; gap:12px; margin-bottom:22px; }
            .auth-brand img { height:56px; width:auto; object-fit:contain; }
            .auth-brand .auth-brand-name { font-size:18px; font-weight:600; color:#1f2937; letter-spacing:.01em; }

            .auth-card {
                width:100%; max-width:420px; background:#fff;
                border:1px solid rgba(0,0,0,.05); border-radius:18px;
                box-shadow:0 18px 50px rgba(0,0,0,.10);
                padding:30px 28px; position:relative; overflow:hidden;
            }
            .auth-card::before {
                content:""; position:absolute; inset:0 0 auto 0; height:4px;
                background:linear-gradient(90deg, var(--brand, #F58220), var(--accent, #F58220));
            }
            .auth-foot { margin-top:20px; text-align:center; font-size:12px; color:#9ca3af; }
        </style>
    </head>
    <body class="antialiased">
        <div class="auth-shell">
            <a href="/" class="auth-brand">
                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->name }}">
                <span class="auth-brand-name">{{ $branding->name }}</span>
            </a>

            <div class="auth-card">
                {{ $slot }}
            </div>

            <p class="auth-foot">&copy; {{ date('Y') }} {{ $branding->name }}</p>
        </div>
    </body>
</html>
