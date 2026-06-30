<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $current->title }} – Dokumentacija</title>
    <link rel="stylesheet" href="{{ asset('css/tools.css') }}?v={{ filemtime(public_path('css/tools.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}" />
    @include('partials.branding-head')

    <style>
        .docs-breadcrumb { display:flex; flex-wrap:wrap; gap:6px; align-items:center; justify-content:center; font-size:14px; margin-bottom:10px; }
        .docs-breadcrumb a { color:var(--brand, #F58220); text-decoration:none; }
        .docs-breadcrumb .sep { opacity:.5; }
        .docs-breadcrumb .current { opacity:.7; }

        /* TABS (sub-sections) */
        .sec-tabs { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:14px; margin-top:18px; }
        .sec-card {
            display:flex; justify-content:center; align-items:center; text-align:center;
            font-weight:600; border:2px solid #f2f2f2; border-radius:14px; padding:22px 18px;
            background:#fff; cursor:pointer; transition:all .2s ease; box-shadow:0 2px 6px rgba(0,0,0,.07);
        }
        .sec-card:hover { transform:translateY(-2px); box-shadow:0 3px 8px rgba(0,0,0,.1); }
        .sec-card.active {
            border-color:color-mix(in srgb, var(--brand, #F58220) 48%, transparent); color:var(--brand, #F58220);
            box-shadow:0 2px 8px rgba(245,130,32,.18);
        }

        .sec-panel { display:none; margin-top:22px; }
        .sec-panel.active { display:block; }

        .doc-thumb { max-height:90px; max-width:100%; object-fit:contain; margin-bottom:8px; border-radius:8px; }
        .doc-kind { display:inline-block; font-size:11px; opacity:.55; margin-top:6px; text-transform:uppercase; letter-spacing:.05em; }
        .panel-subsections { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; margin-bottom:14px; }
        .panel-subsections a { font-size:13px; color:var(--brand, #F58220); text-decoration:none; border:1px solid color-mix(in srgb, var(--brand, #F58220) 33%, transparent); border-radius:20px; padding:4px 12px; }
    </style>
</head>

<body>
<header>
    <div class="logo">
        <img src="{{ $branding->logoUrl }}" alt="{{ $branding->name }}">
    </div>
    <nav>
        <div class="menu-toggle"><i class="fas fa-bars"></i></div>
        <ul class="nav-menu">
            <li><a href="{{ url('/tools') }}">Web Tools</a></li>
            <li><a href="{{ url('/apps') }}">App Downloads</a></li>
            <li><a class="clicked" href="{{ route('docs.index') }}">Dokumentacija</a></li>
            <li><a href="{{ route('imenik.index') }}">Imenik</a></li>
        </ul>
    </nav>
    @include('partials.user-menu')
</header>

<main>
    <section class="hero" id="top" style="display:flex; flex-direction:column; align-items:center;">
        <nav class="docs-breadcrumb">
            @foreach($breadcrumb as $crumb)
                @if(!$loop->first)<span class="sep">›</span>@endif
                @if($crumb->url)
                    <a href="{{ $crumb->url }}">{{ $crumb->title }}</a>
                @else
                    <span class="current">{{ $crumb->title }}</span>
                @endif
            @endforeach
        </nav>

        <h2 style="margin:6px 0;">{{ $current->title }}</h2>
        @if(!empty($current->summary))
            <p style="opacity:.8; text-align:center; margin:0;">{{ $current->summary }}</p>
        @endif
        @if(!empty($current->description))
            <p style="opacity:.7; text-align:center; margin:8px auto 0; max-width:760px; white-space:pre-line;">{{ $current->description }}</p>
        @endif
    </section>

    {{-- This section's own documents (shown above the tabs, if any) --}}
    @if($attachments->count())
        <div class="grid-container" style="grid-template-columns:repeat(auto-fill, minmax(240px,1fr)); margin-top:18px;">
            @foreach($attachments as $att)
                <a href="{{ $att->href }}" target="_blank" rel="noopener noreferrer" class="grid-item">
                    @if($att->is_image)<img src="{{ $att->href }}" alt="{{ $att->label }}" class="doc-thumb" loading="lazy">@endif
                    <span>{{ $att->label }}</span>
                    <span class="doc-kind">{{ $att->kind }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Sub-sections as TABS; clicking shows that category's documents below --}}
    @if($children->count())
        <div class="sec-tabs">
            @foreach($children as $child)
                <div class="sec-card" tabindex="0" data-sec="{{ $child->slug }}" aria-controls="sec-{{ $child->slug }}">
                    <span>{{ $child->title }}</span>
                </div>
            @endforeach
        </div>

        @foreach($children as $child)
            <div id="sec-{{ $child->slug }}" class="sec-panel" role="region">
                @if(!empty($child->summary))
                    <p style="text-align:center; opacity:.8; margin-bottom:10px;">{{ $child->summary }}</p>
                @endif

                {{-- deeper sub-sections (if any) as quick links --}}
                @if($child->activeChildren->count())
                    <div class="panel-subsections">
                        @foreach($child->activeChildren as $grandchild)
                            <a href="{{ route('docs.show', $grandchild->slug) }}">{{ $grandchild->title }} ›</a>
                        @endforeach
                    </div>
                @endif

                @if($child->attachments->count())
                    <div class="grid-container" style="grid-template-columns:repeat(auto-fill, minmax(240px,1fr));">
                        @foreach($child->attachments as $att)
                            <a href="{{ $att->href }}" target="_blank" rel="noopener noreferrer" class="grid-item">
                                @if($att->is_image)<img src="{{ $att->href }}" alt="{{ $att->label }}" class="doc-thumb" loading="lazy">@endif
                                <span>{{ $att->label }}</span>
                                <span class="doc-kind">{{ $att->kind }}</span>
                            </a>
                        @endforeach
                    </div>
                @elseif(!$child->activeChildren->count())
                    <p class="small" style="text-align:center; opacity:.7;">Nema dokumenata u ovoj sekciji.</p>
                @endif
            </div>
        @endforeach
    @elseif(!$attachments->count())
        <p class="small" style="text-align:center; opacity:.7; margin-top:26px;">Trenutno nema sadržaja u ovoj sekciji.</p>
    @endif
</main>

<script>
(function () {
    function selectSection(slug, opts) {
        const options = Object.assign({ updateHash: true }, opts || {});
        document.querySelectorAll('.sec-card').forEach(c => c.classList.toggle('active', c.dataset.sec === slug));
        document.querySelectorAll('.sec-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('sec-' + slug);
        if (panel) panel.classList.add('active');
        if (options.updateHash) {
            const h = '#sec=' + encodeURIComponent(slug);
            if (location.hash !== h) history.replaceState(null, '', h);
        }
    }

    function init() {
        const cards = document.querySelectorAll('.sec-card');
        if (!cards.length) return;

        cards.forEach(card => {
            card.addEventListener('click', () => selectSection(card.dataset.sec));
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectSection(card.dataset.sec); }
            });
        });

        let initial = null;
        if (location.hash.startsWith('#sec=')) initial = decodeURIComponent(location.hash.split('=')[1] || '');
        if (!initial) initial = cards[0].dataset.sec;
        selectSection(initial, { updateHash: false });
    }

    window.addEventListener('DOMContentLoaded', init);
})();
</script>
</body>
</html>
