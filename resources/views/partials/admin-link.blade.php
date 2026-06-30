{{-- Shown only to logged-in users who can access the back-end (have a back-end role). --}}
@auth
    @if(auth()->user()->hasAnyRole(\App\Models\User::BACKEND_ROLES) || auth()->user()->is_admin)
        <li>
            <a href="{{ url('/admin') }}" title="Otvori backend / dashboard"
               style="display:inline-flex; align-items:center; gap:6px; color:#F58220; font-weight:600;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                </svg>
                <span>Backend</span>
            </a>
        </li>
    @endif
@endauth
