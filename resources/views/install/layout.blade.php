@php($b = branding())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Install' }} — {{ $b->name }}</title>
    <link rel="icon" href="{{ $b->faviconUrl }}">
    <style>
        :root {
            --brand: {{ $b->primary }};
            --brand-dark: color-mix(in srgb, var(--brand) 80%, black);
            --bg: #f4f5f7;
            --card: #ffffff;
            --text: #1f2430;
            --muted: #6b7280;
            --border: #e3e6ea;
            --danger: #dc2626;
            --ok: #16a34a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 16px;
        }
        .wrap { width: 100%; max-width: 560px; }
        .brand-head { text-align: center; margin-bottom: 28px; }
        .brand-head img { max-height: 48px; max-width: 220px; }
        .brand-head h1 { font-size: 1.1rem; color: var(--muted); font-weight: 500; margin: 12px 0 0; }
        .steps { display: flex; gap: 8px; margin-bottom: 24px; }
        .steps .dot {
            flex: 1; height: 6px; border-radius: 999px; background: var(--border);
        }
        .steps .dot.done { background: var(--brand); }
        .steps .dot.active { background: var(--brand); opacity: .55; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.04);
        }
        .card h2 { margin: 0 0 6px; font-size: 1.45rem; }
        .card .lead { color: var(--muted); margin: 0 0 24px; line-height: 1.5; }
        label { display: block; font-weight: 600; font-size: .9rem; margin: 0 0 6px; }
        .field { margin-bottom: 18px; }
        .field .hint { color: var(--muted); font-size: .82rem; margin-top: 5px; }
        input[type=text], input[type=email], input[type=password], input[type=url],
        input[type=number], select {
            width: 100%; padding: 11px 13px; border: 1px solid var(--border);
            border-radius: 9px; font-size: .95rem; background: #fff; color: var(--text);
        }
        input:focus, select:focus { outline: 2px solid var(--brand); outline-offset: 1px; border-color: var(--brand); }
        input[type=color] { width: 54px; height: 40px; padding: 2px; border: 1px solid var(--border); border-radius: 9px; vertical-align: middle; }
        input[type=file] { font-size: .9rem; }
        .row { display: flex; gap: 14px; }
        .row > * { flex: 1; }
        .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 26px; gap: 12px; }
        .btn {
            display: inline-block; border: 0; cursor: pointer; font-size: .95rem; font-weight: 600;
            padding: 12px 22px; border-radius: 9px; text-decoration: none;
            background: var(--brand); color: #fff;
        }
        .btn:hover { background: var(--brand-dark); }
        .btn-ghost { background: transparent; color: var(--muted); padding: 12px 6px; }
        .btn-ghost:hover { background: transparent; color: var(--text); }
        .err { color: var(--danger); font-size: .82rem; margin-top: 5px; }
        .alert { padding: 12px 14px; border-radius: 9px; font-size: .9rem; margin-bottom: 18px; }
        .alert-ok { background: #ecfdf3; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .checkbox { display: flex; align-items: center; gap: 8px; }
        .checkbox input { width: auto; }
        .checkbox label { margin: 0; font-weight: 500; }
        .foot { text-align: center; color: var(--muted); font-size: .8rem; margin-top: 22px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand-head">
            <img src="{{ $b->logoUrl }}" alt="{{ $b->name }}">
            <h1>Setup wizard</h1>
        </div>

        @php($total = 4)
        <div class="steps">
            @for ($i = 1; $i <= $total; $i++)
                <div class="dot {{ $i < ($step ?? 1) ? 'done' : ($i == ($step ?? 1) ? 'active' : '') }}"></div>
            @endfor
        </div>

        <div class="card">
            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-err">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>

        <div class="foot">Step {{ $step ?? 1 }} of {{ $total }}</div>
    </div>
</body>
</html>
