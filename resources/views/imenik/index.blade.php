<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Imenik</title>
    <link rel="stylesheet" href="{{ asset('css/tools.css') }}?v={{ filemtime(public_path('css/tools.css')) }}" />
    @include('partials.branding-head')
    <style>
        :root { --line:#ececec; --muted:#6b7280; }

        .pb-wrap { max-width:1000px; margin:0 auto; padding:0 16px 48px; }

        .pb-head { text-align:center; margin:10px 0 20px; }
        .pb-head h2 { margin:6px 0 4px; font-size:26px; }
        .pb-head p { margin:0; color:var(--muted); font-size:14px; }

        /* TOOLBAR */
        .pb-toolbar {
            display:flex; flex-wrap:wrap; gap:12px; align-items:center;
            margin:0 auto 18px;
        }
        /* search grows; the search box is a pill with an icon inside */
        .pb-search { position:relative; flex:1 1 320px; min-width:240px; }
        .pb-search .pb-search-ico {
            position:absolute; left:16px; top:50%; transform:translateY(-50%);
            color:var(--muted); pointer-events:none;
        }
        .pb-search input {
            width:100%; padding:13px 44px 13px 44px; border:1px solid #e2e2e2;
            border-radius:999px; font-size:15px; outline:none; background:#fff;
            transition:border .15s, box-shadow .15s;
        }
        .pb-search input:focus {
            border-color:var(--brand, #F58220);
            box-shadow:0 0 0 3px color-mix(in srgb, var(--brand, #F58220) 18%, transparent);
        }
        /* clear (×) button inside the field */
        .pb-search .pb-clear {
            position:absolute; right:8px; top:50%; transform:translateY(-50%);
            width:28px; height:28px; border:none; background:transparent; color:var(--muted);
            font-size:20px; line-height:1; cursor:pointer; border-radius:50%; display:none;
        }
        .pb-search .pb-clear:hover { background:#f3f4f6; color:#111; }
        .pb-search.has-value .pb-clear { display:inline-flex; align-items:center; justify-content:center; }

        .pb-actions { display:flex; align-items:center; gap:10px; }
        .pb-result-count { color:var(--muted); font-size:13px; white-space:nowrap; }

        .pb-btn {
            display:inline-flex; align-items:center; gap:7px; padding:12px 18px; border:none;
            border-radius:999px; background:var(--brand, #F58220); color:#fff; font-weight:600;
            font-size:14px; cursor:pointer; text-decoration:none; white-space:nowrap;
            transition:transform .12s, box-shadow .12s;
        }
        .pb-btn:hover { transform:translateY(-1px); box-shadow:0 6px 16px color-mix(in srgb, var(--brand, #F58220) 30%, transparent); }
        .pb-btn.ghost { background:#fff; color:#374151; border:1px solid #e2e2e2; }
        .pb-btn.ghost:hover { box-shadow:0 6px 16px rgba(0,0,0,.08); }

        /* TABLE (desktop) */
        .pb-card { background:#fff; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,.06); overflow:hidden; }
        table.pb { width:100%; border-collapse:collapse; }
        table.pb th, table.pb td { padding:14px 18px; text-align:left; font-size:14.5px; }
        table.pb thead th {
            background:#fafafa; color:#374151; font-weight:600; border-bottom:1px solid var(--line);
            position:sticky; top:0; z-index:1;
        }
        table.pb tbody tr { border-bottom:1px solid var(--line); transition:background .12s; }
        table.pb tbody tr:last-child { border-bottom:none; }
        table.pb tbody tr:hover { background:color-mix(in srgb, var(--brand, #F58220) 5%, #fff); }
        .pb-name { font-weight:600; }
        .pb-meta { color:var(--muted); }
        .pb-call {
            display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border-radius:999px;
            background:color-mix(in srgb, var(--brand, #F58220) 10%, transparent);
            color:var(--brand, #F58220); font-weight:700; text-decoration:none; white-space:nowrap;
        }
        .pb-call:hover { background:color-mix(in srgb, var(--brand, #F58220) 18%, transparent); }
        .pb-hidden {
            display:inline-block; font-size:10px; text-transform:uppercase; letter-spacing:.04em;
            color:#b91c1c; border:1px solid #fecaca; border-radius:999px; padding:1px 8px;
            margin-left:8px; vertical-align:middle;
        }
        .pb-empty { text-align:center; color:var(--muted); padding:44px 16px; }
        .pb-empty[hidden] { display:none; }

        /* MOBILE → cards */
        @media (max-width:640px) {
            .pb-toolbar { flex-direction:column; align-items:stretch; }
            .pb-search { flex:0 0 auto; width:100%; }
            .pb-actions { justify-content:space-between; }
            table.pb thead { display:none; }
            table.pb, table.pb tbody, table.pb tr, table.pb td { display:block; width:100%; }
            table.pb tbody tr { padding:14px 16px; border-bottom:1px solid var(--line); }
            table.pb td { padding:3px 0; border:none; }
            table.pb td.pb-call-cell { margin-top:10px; }
            table.pb td[data-label]::before {
                content:attr(data-label) " "; color:var(--muted); font-size:12px;
                text-transform:uppercase; letter-spacing:.03em; display:block;
            }
            .pb-name { font-size:17px; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo"><img src="{{ $branding->logoUrl }}" alt="{{ $branding->name }}"></div>
    <nav>
        <div class="menu-toggle">
            <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </div>
        <ul class="nav-menu">
            <li><a href="{{ url('/tools') }}">Web Tools</a></li>
            <li><a href="{{ url('/apps') }}">App Downloads</a></li>
            <li><a href="{{ route('docs.index') }}">Dokumentacija</a></li>
            <li><a class="clicked" href="{{ route('imenik.index') }}">Imenik</a></li>
        </ul>
    </nav>
    @include('partials.user-menu', ['showLogin' => true])
</header>

<main class="pb-wrap">
    <div class="pb-head">
        <h2>Imenik</h2>
        <p>Pretraži po imenu, broju, odjelu ili centru.</p>
    </div>

    <div class="pb-toolbar">
        {{-- Live, client-side filtering: no submit / Enter needed. The text input
             is not wrapped in a form so typing never reloads the page. --}}
        <div class="pb-search" id="pbSearchWrap">
            <span class="pb-search-ico">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
            <input type="text" id="pbSearch" value="{{ $q }}" placeholder="Pretraži imenik…" autocomplete="off" autofocus>
            <button type="button" class="pb-clear" id="pbClear" aria-label="Očisti">&times;</button>
        </div>

        <div class="pb-actions">
            <span class="pb-result-count" id="pbCount"></span>
            @auth
                @if($canExport)
                    {{-- Export is the full inventory (incl. free numbers), so it is intentionally
                         NOT tied to the on-screen live filter. --}}
                    <a class="pb-btn" id="pbExport" href="{{ route('imenik.export') }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        CSV
                    </a>
                @endif
            @endauth
        </div>
    </div>

    <div class="pb-card">
        <table class="pb">
            <thead>
                <tr><th>Djelatnik</th><th>Odjel</th><th>Centar</th><th>Broj</th></tr>
            </thead>
            <tbody id="pbBody">
                @foreach($numbers as $n)
                    @php
                        $name   = $n->employee?->full_name ?? '';
                        $dept   = $n->employee?->department?->name ?? '';
                        $center = $n->employee?->center?->name ?? '';
                        $digits = preg_replace('/\D+/', '', (string) $n->number);
                        // Lowercased haystack the live filter matches every term against.
                        $hay = mb_strtolower(trim("{$name} {$dept} {$center} {$n->number} {$digits}"));
                        // Also append an accent-folded copy (č→c, ć→c, ž→z, š→s, đ→d) so an
                        // ASCII query ("pericic") still matches a diacritic name ("Peričić").
                        $folded = strtr($hay, ['č' => 'c', 'ć' => 'c', 'ž' => 'z', 'š' => 's', 'đ' => 'd']);
                        $hay = $folded === $hay ? $hay : "{$hay} {$folded}";
                    @endphp
                    <tr data-search="{{ $hay }}">
                        <td class="pb-name" data-label="Djelatnik">{{ $name !== '' ? $name : '—' }}</td>
                        <td class="pb-meta" data-label="Odjel">{{ $dept !== '' ? $dept : '—' }}</td>
                        <td class="pb-meta" data-label="Centar">{{ $center !== '' ? $center : '—' }}</td>
                        <td class="pb-call-cell" data-label="Broj">
                            <a class="pb-call" href="tel:{{ preg_replace('/[^0-9+]/', '', $n->number) }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.7 2.34a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.74-1.74a2 2 0 0 1 2.11-.45c.74.34 1.53.57 2.34.7A2 2 0 0 1 22 16.92z"/></svg>
                                {{ $n->number }}
                            </a>
                            @if(! $n->is_public)<span class="pb-hidden">skriven</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="pb-empty" id="pbEmpty" {{ $numbers->count() ? 'hidden' : '' }}>Nema rezultata.</p>
    </div>
</main>

<script src="{{ asset('js/tools.js') }}"></script>
<script>
    (function () {
        var input  = document.getElementById('pbSearch');
        var clear  = document.getElementById('pbClear');
        var wrap   = document.getElementById('pbSearchWrap');
        var body   = document.getElementById('pbBody');
        var empty  = document.getElementById('pbEmpty');
        var count  = document.getElementById('pbCount');
        var rows   = body ? Array.prototype.slice.call(body.querySelectorAll('tr')) : [];

        function plural(n) { return n === 1 ? 'broj' : 'brojeva'; }

        function apply() {
            var raw = input.value.trim().toLowerCase();
            // Every whitespace-separated term must match (AND search).
            var terms = raw.length ? raw.split(/\s+/) : [];
            var visible = 0;

            rows.forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                var match = terms.every(function (t) { return hay.indexOf(t) !== -1; });
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (empty) empty.hidden = visible !== 0;
            if (count) count.textContent = visible + ' ' + plural(visible);
            wrap.classList.toggle('has-value', input.value.length > 0);
        }

        input.addEventListener('input', apply);
        clear.addEventListener('click', function () { input.value = ''; input.focus(); apply(); });
        apply(); // initialise count + state on load
    })();
</script>
</body>
</html>
