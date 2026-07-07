<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements \Filament\Auth\Http\Responses\Contracts\LoginResponse
{
    /**
     * Always send everyone to the panel dashboard, ignoring any stale
     * "intended" URL (which previously bounced people to /pulse). NOT the
     * Users list — that requires view_users, which restricted roles (e.g.
     * docs_manager) don't hold, and would 403 them right after login.
     */
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->to(route('filament.admin.pages.dashboard'));
    }
}
