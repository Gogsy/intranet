<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>App Downloads</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}" />
    @include('partials.branding-head')
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" /> -->
	<!-- 
  Fonts used on this site are provided by Google Fonts 
  and are licensed under the Open Font License (OFL). 
  See: https://fonts.google.com/ for more information.
-->
<!-- 
  Roboto and Material Icons are loaded from Google Fonts (https://fonts.google.com/).
  These are licensed under the Open Font License (OFL) and Apache License 2.0 respectively.
-->
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
                <li><a class="clicked" href="{{ url('/apps') }}">App Downloads</a></li>
                <li><a href="{{ route('docs.index') }}">Dokumentacija</a></li>
                <li><a href="{{ route('imenik.index') }}">Imenik</a></li>
            </ul>
        </nav>
        @include('partials.user-menu')
    </header>

    <main>
        <div class="grid-container">
            @foreach ($apps as $app)
                @php $iconPath = $app->icon_url; @endphp

                <div class="grid-item"
                    data-name="{{ $app['name'] }}"
                    @if ($app->download_url) data-link="{{ $app->download_url }}" @endif
                    @if ($iconPath) data-icon="{{ $iconPath }}" @endif
                    @if (!empty($app['pdf_installation_instructions']))
                        data-install="{{ asset('storage/' . $app['pdf_installation_instructions']) }}"
                    @endif
                    @if (!empty($app['pdf_user_manual']))
                        data-usage="{{ asset('storage/' . $app['pdf_user_manual']) }}"
                    @endif
                >
                    @if ($iconPath)
                        <img src="{{ $iconPath }}" alt="{{ $app['name'] }} Icon" class="app-icon">
                    @endif
                    <span>{{ $app['name'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="expanded-view" id="expandedView">
            <div class="expanded-content">
                <h2 id="expandedTitle"></h2>
                <img id="expandedIcon" src="" alt="App Icon" class="app-icon">
                <button class="option-btn" id="installGuide">
                    Upute za instalaciju
                </button>
                <button class="option-btn" id="usageGuide">
                    Upute za korištenje
                </button>
                <button class="option-btn" id="downloadApp">
                    Download aplikacije
                </button>
                <button class="close-btn">Close</button>
            </div>
        </div>
    </main>

    <script src="{{ asset('js/scriptFront.js') }}"></script>
</body>

</html>
