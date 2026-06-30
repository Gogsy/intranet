{{-- Shared branding head: injects CSS vars + favicon + logo sizing, driven by AppSetting. --}}
<style>
    :root { --brand: {{ $branding->primary }}; --accent: {{ $branding->accent }}; }
    header .logo img { height: {{ $branding->logoHeight }}px; width: auto; max-width: 100%; }
</style>
<link rel="icon" href="{{ $branding->faviconUrl }}">
