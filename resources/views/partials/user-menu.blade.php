{{-- Reusable user menu: avatar + dropdown for authenticated users.
     Guests see NOTHING unless this partial is included with ['showLogin' => true]
     (only the Imenik page does that). Login is otherwise reachable solely via /login. --}}
@auth
    @php
        $u = auth()->user();
        $name = $u->name ?? $u->email ?? 'Korisnik';
        $parts = preg_split('/\s+/', trim($name));
        $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
        $avatarUrl = $u->avatar_url ?? null;
        $canBackend = $u->hasAnyRole(\App\Models\User::BACKEND_ROLES) || $u->is_admin;
    @endphp

    <div class="user-menu">
        <details class="um-dropdown">
            <summary class="um-trigger" title="{{ $name }}">
                <span class="um-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                    @else
                        {{ $initials ?: '?' }}
                    @endif
                </span>
                <span class="um-name">{{ $name }}</span>
                <svg class="um-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </summary>

            <div class="um-panel">
                <div class="um-panel-head">
                    <span class="um-avatar um-avatar-lg">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                        @else
                            {{ $initials ?: '?' }}
                        @endif
                    </span>
                    <div class="um-panel-id">
                        <strong>{{ $name }}</strong>
                        @if($u->email)<span>{{ $u->email }}</span>@endif
                    </div>
                </div>

                @if($canBackend)
                    <a class="um-item" href="{{ url('/admin') }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
                        <span>Backend / Dashboard</span>
                    </a>
                @endif

                {{-- Logout form: standalone, NOT nested inside any other form. --}}
                <form method="POST" action="{{ route('logout') }}" class="um-logout">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    <button type="submit" class="um-item um-item-danger">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Odjava</span>
                    </button>
                </form>
            </div>
        </details>
    </div>
@endauth

@guest
    @if(($showLogin ?? false) === true)
        <div class="user-menu">
            <a class="um-login" href="{{ route('login', ['redirect' => url()->current()]) }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                <span>Prijava</span>
            </a>
        </div>
    @endif
@endguest

@once
<style>
    .user-menu { list-style:none; position:relative; }
    .um-dropdown { position:relative; }
    .um-dropdown > summary { list-style:none; cursor:pointer; }
    .um-dropdown > summary::-webkit-details-marker { display:none; }

    .um-trigger {
        display:inline-flex; align-items:center; gap:8px;
        padding:5px 10px 5px 5px; border-radius:999px;
        background:rgba(0,0,0,.04); transition:background .15s;
    }
    .um-trigger:hover { background:rgba(0,0,0,.08); }
    .um-caret { opacity:.55; transition:transform .15s; flex-shrink:0; }
    .um-dropdown[open] .um-caret { transform:rotate(180deg); }

    .um-avatar {
        display:inline-flex; align-items:center; justify-content:center;
        width:34px; height:34px; border-radius:50%; flex-shrink:0;
        background:var(--brand, #F58220); color:#fff;
        font-weight:700; font-size:13px; line-height:1; overflow:hidden;
    }
    .um-avatar img { width:100%; height:100%; object-fit:cover; }
    .um-avatar-lg { width:42px; height:42px; font-size:15px; }
    .um-name { font-weight:600; font-size:14px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    .um-panel {
        position:absolute; right:0; top:calc(100% + 10px); z-index:1000;
        min-width:240px; background:#fff; border-radius:14px;
        box-shadow:0 12px 34px rgba(0,0,0,.16); border:1px solid rgba(0,0,0,.06);
        padding:8px; animation:um-pop .12s ease;
    }
    @keyframes um-pop { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:none; } }

    .um-panel-head { display:flex; align-items:center; gap:10px; padding:8px 10px 12px; border-bottom:1px solid rgba(0,0,0,.07); margin-bottom:6px; }
    .um-panel-id { display:flex; flex-direction:column; min-width:0; }
    .um-panel-id strong { font-size:14px; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .um-panel-id span { font-size:12px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    .um-item {
        display:flex; align-items:center; gap:10px; width:100%;
        padding:9px 10px; border-radius:9px; border:none; background:none;
        font:inherit; font-size:14px; color:#374151; text-decoration:none;
        cursor:pointer; text-align:left; transition:background .12s, color .12s;
    }
    .um-item:hover { background:rgba(245,130,32,.1); color:var(--brand, #F58220); }
    .um-item svg { flex-shrink:0; }
    .um-item-danger:hover { background:rgba(220,38,38,.08); color:#dc2626; }
    .um-logout { margin:0; }

    .um-login {
        display:inline-flex; align-items:center; gap:7px;
        padding:8px 14px; border-radius:999px;
        background:var(--brand, #F58220); color:#fff;
        font-weight:600; font-size:14px; text-decoration:none;
        transition:transform .12s, box-shadow .12s;
    }
    .um-login:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(245,130,32,.3); }

    @media (max-width:640px) {
        .um-name { max-width:90px; }
        .um-panel { right:auto; left:0; }
    }
</style>
<script>
    /* Close any open user-menu dropdown when clicking outside it. */
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.um-dropdown[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
</script>
@endonce
