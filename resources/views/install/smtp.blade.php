@extends('install.layout', ['title' => 'Email / SMTP'])

@section('content')
    <h2>Email settings</h2>
    <p class="lead">
        Configure SMTP so the app can send invites and password resets. Works with Gmail /
        Google Workspace, Microsoft 365 / Outlook, or any SMTP server. This step is optional —
        you can skip it and set it up later.
    </p>

    <form method="POST" action="{{ route('install.smtp.store') }}">
        @csrf

        <div class="row">
            <div class="field">
                <label for="host">SMTP host</label>
                <input type="text" id="host" name="host" value="{{ old('host', $settings->host) }}" placeholder="smtp.gmail.com">
                @error('host') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="field" style="max-width: 130px;">
                <label for="port">Port</label>
                <input type="number" id="port" name="port" value="{{ old('port', $settings->port ?: 587) }}">
                @error('port') <div class="err">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field">
            <label for="encryption">Encryption</label>
            <select id="encryption" name="encryption">
                @php($enc = old('encryption', $settings->encryption ?: 'tls'))
                <option value="tls" @selected($enc === 'tls')>TLS</option>
                <option value="ssl" @selected($enc === 'ssl')>SSL</option>
                <option value="none" @selected($enc === null && old('encryption') === 'none')>None</option>
            </select>
        </div>

        <div class="row">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="{{ old('username', $settings->username) }}" placeholder="you@company.com">
            </div>
            <div class="field">
                <label for="password">Password / App password</label>
                <input type="password" id="password" name="password" placeholder="•••••••• (leave blank to keep)">
                <div class="hint">Gmail/Workspace needs an App Password (2FA on).</div>
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label for="from_address">From address</label>
                <input type="email" id="from_address" name="from_address" value="{{ old('from_address', $settings->from_address) }}" placeholder="noreply@company.com">
                @error('from_address') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="from_name">From name</label>
                <input type="text" id="from_name" name="from_name" value="{{ old('from_name', $settings->from_name) }}" placeholder="{{ branding()->name }}">
            </div>
        </div>

        <div class="field checkbox">
            <input type="checkbox" id="send_test" name="send_test" value="1">
            <label for="send_test">Send a test email after saving to:</label>
        </div>
        <div class="field">
            <input type="email" name="test_to" value="{{ old('test_to') }}" placeholder="you@company.com">
        </div>

        <div class="actions">
            <a href="{{ route('install.admin') }}" class="btn btn-ghost">← Back</a>
            <button type="submit" class="btn">Save &amp; continue →</button>
        </div>
    </form>
@endsection
