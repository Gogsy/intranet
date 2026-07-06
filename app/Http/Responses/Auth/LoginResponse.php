<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements \Filament\Auth\Http\Responses\Contracts\LoginResponse
{
    /**
     * Always send admins to the panel home (Users list), ignoring any stale
     * "intended" URL (which previously bounced people to /pulse).
     */
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->to(route('filament.admin.resources.users.index'));
    }
}
