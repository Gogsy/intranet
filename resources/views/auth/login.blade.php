<x-guest-layout>
    @php
        // Where to send the user after login: explicit ?redirect, else the page they came from.
        $redirectTarget = request('redirect') ?? url()->previous();
    @endphp

    <h1 style="margin:0 0 4px; font-size:22px; font-weight:600; color:#111827;">Prijava</h1>
    <p style="margin:0 0 20px; font-size:14px; color:#6b7280;">Unesite svoje podatke za pristup.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <input type="hidden" name="redirect" value="{{ $redirectTarget }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 shadow-sm" name="remember"
                       style="color:var(--brand, #F58220);">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            @if (Route::has('password.request'))
                <a class="text-sm text-gray-600 hover:text-gray-900 underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @else
                <span></span>
            @endif

            <button type="submit"
                    style="display:inline-flex; align-items:center; justify-content:center; padding:10px 22px; border:none; border-radius:999px; background:var(--brand, #F58220); color:#fff; font-weight:600; font-size:14px; cursor:pointer; transition:transform .12s, box-shadow .12s;"
                    onmouseover="this.style.boxShadow='0 6px 16px rgba(245,130,32,.3)';this.style.transform='translateY(-1px)';"
                    onmouseout="this.style.boxShadow='none';this.style.transform='none';">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
</x-guest-layout>
