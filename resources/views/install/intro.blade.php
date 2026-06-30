@extends('install.layout', ['title' => 'Welcome'])

@section('content')
    <h2>Welcome 👋</h2>
    <p class="lead">
        Let's get your application set up. This quick wizard will help you create your
        administrator account, configure email, branding, and the app download — in just
        a few steps.
    </p>

    <ol style="color: var(--muted); line-height: 1.9; padding-left: 18px; margin: 0 0 6px;">
        <li>Create your super administrator account</li>
        <li>Configure email (SMTP) — optional</li>
        <li>Set your branding (name, logo, color)</li>
        <li>Add your app download link</li>
    </ol>

    <div class="actions">
        <span></span>
        <a href="{{ route('install.admin') }}" class="btn">Get started →</a>
    </div>
@endsection
