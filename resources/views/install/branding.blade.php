@extends('install.layout', ['title' => 'Branding'])

@section('content')
    <h2>Branding</h2>
    <p class="lead">Make it yours. Set your app name, upload a logo, and pick your primary color.</p>

    <form method="POST" action="{{ route('install.branding.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="app_name">App name</label>
            <input type="text" id="app_name" name="app_name" value="{{ old('app_name', $settings->app_name ?: $settings->name) }}" required>
            @error('app_name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="company_name">Company name</label>
            <input type="text" id="company_name" name="company_name" value="{{ old('company_name', $settings->company_name) }}">
        </div>

        <div class="field">
            <label for="logo">Logo</label>
            <input type="file" id="logo" name="logo" accept="image/*">
            <div class="hint">PNG, JPG or SVG, up to 4&nbsp;MB. Leave empty to keep the current logo.</div>
            @error('logo') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="primary_color">Primary color</label>
            <input type="color" id="primary_color" name="primary_color" value="{{ old('primary_color', $settings->primary) }}">
            @error('primary_color') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="actions">
            <a href="{{ route('install.smtp') }}" class="btn btn-ghost">← Back</a>
            <button type="submit" class="btn">Finish setup ✓</button>
        </div>
    </form>
@endsection
