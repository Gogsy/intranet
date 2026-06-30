@extends('install.layout', ['title' => 'Administrator'])

@section('content')
    <h2>Create your admin</h2>
    <p class="lead">This account has full access (super admin). You'll use it to sign in to the admin panel.</p>

    <form method="POST" action="{{ route('install.admin.store') }}">
        @csrf

        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
            @error('name') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
            @error('email') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="row">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="hint">At least 8 characters.</div>
                @error('password') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
        </div>

        <div class="actions">
            <a href="{{ route('install.index') }}" class="btn btn-ghost">← Back</a>
            <button type="submit" class="btn">Continue →</button>
        </div>
    </form>
@endsection
