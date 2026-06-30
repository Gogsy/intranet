<x-guest-layout>
    <h1 style="margin:0 0 4px; font-size:22px; font-weight:600; color:#111827;">Zaboravljena lozinka</h1>
    <p style="margin:0 0 20px; font-size:14px; color:#6b7280;">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <button type="submit"
                    style="display:inline-flex; align-items:center; justify-content:center; padding:10px 22px; border:none; border-radius:999px; background:var(--brand, #F58220); color:#fff; font-weight:600; font-size:14px; cursor:pointer; transition:transform .12s, box-shadow .12s;"
                    onmouseover="this.style.boxShadow='0 6px 16px rgba(245,130,32,.3)';this.style.transform='translateY(-1px)';"
                    onmouseout="this.style.boxShadow='none';this.style.transform='none';">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
</x-guest-layout>
