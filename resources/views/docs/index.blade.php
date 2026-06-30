<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dokumentacija</title>

    <link rel="stylesheet" href="{{ asset('css/tools.css') }}?v={{ filemtime(public_path('css/tools.css')) }}" />
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ filemtime(public_path('css/docs.css')) }}" />

    @include('partials.branding-head')
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

<main class="docs-index">
    <section class="hero">
        <p>Politike, procedure i standardi</p>
    </section>

    <div class="grid-container">
        @foreach ($docs as $doc)
            <a href="{{ route('docs.show', $doc->slug) }}" class="grid-item">
                <span style="font-weight:600;">{{ $doc->title }}</span>

                @if(!empty($doc->summary))
                    <div class="small text-muted" style="opacity:.8; margin-top:6px;">
                        {{ $doc->summary }}
                    </div>
                @endif
            </a>
        @endforeach
    </div>
</main>

<script src="{{ asset('js/tools.js') }}"></script>
</body>
</html>
