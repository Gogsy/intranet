<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Per-user replacement for Filament's EnsureMultiFactorAuthenticationIsEnabled.
 *
 * The panel is set up with multi-factor auth "required" so the setup route and
 * this guard are wired in, but MFA is only actually forced on users a super
 * admin flags via `mfa_required` (User form → "Zahtijevaj MFA"). Everyone else
 * passes straight through and MFA stays optional (they can still enable it on
 * their profile). A flagged user with no MFA method enabled is bounced to the
 * MFA setup page until they configure one.
 */
class EnsureMfaForFlaggedUsers
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        // Not flagged → MFA optional, nothing to enforce.
        if (! ($user?->mfa_required)) {
            return $next($request);
        }

        // Flagged and at least one provider already set up → allow.
        foreach (Filament::getMultiFactorAuthenticationProviders() as $provider) {
            if ($provider->isEnabled($user)) {
                return $next($request);
            }
        }

        // Flagged but no MFA yet → force them to the setup page.
        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
