<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Web Tools</title>
    {{-- cache-busting da CSS uvijek uđe svjež --}}
    <link rel="stylesheet" href="{{ asset('css/tools.css') }}?v={{ filemtime(public_path('css/tools.css')) }}" />
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
            <li><a class="clicked" href="{{ url('/tools') }}">Web Tools</a></li>
            <li><a href="{{ url('/apps') }}">App Downloads</a></li>
            <li><a href="{{ route('docs.index') }}">Dokumentacija</a></li>
            <li><a href="{{ route('imenik.index') }}">Imenik</a></li>
        </ul>
    </nav>
    @include('partials.user-menu')
</header>

<main>
    <section class="hero">
        <p>
            Ova stranica sadrži sve interne web alate i resurse firme.
            Klikni na ikonu da otvoriš željeni alat u novom prozoru.
        </p>
    </section>

    <div class="grid-container">
        @foreach ($tools as $tool)
            <a href="{{ $tool->url }}"
               target="_blank"
               rel="noopener noreferrer"
               class="grid-item">
                @if ($tool->icon_url)
                    <img src="{{ $tool->icon_url }}" alt="{{ $tool->name }} Icon" class="app-icon" loading="lazy">
                @endif
                <span>{{ $tool->name }}</span>
            </a>
        @endforeach
    </div>
</main>

<script src="{{ asset('js/tools.js') }}"></script>
</body>
</html>
