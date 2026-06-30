<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Honor a safe, local "redirect" target so logging in from a page returns there.
        $target = $this->safeLocalRedirect($request, $request->input('redirect'));

        return redirect()->intended($target ?? route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Return to the page the user logged out from, if it is a safe local URL.
        $target = $this->safeLocalRedirect($request, $request->input('redirect_to'));

        return redirect($target ?? '/');
    }

    /**
     * Validate a requested redirect URL against open-redirect attacks.
     * Accepts only same-host absolute URLs or relative paths; returns a relative
     * path (path + query) on success, or null when unsafe/empty.
     */
    protected function safeLocalRedirect(Request $request, ?string $candidate): ?string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            return null;
        }

        // Reject protocol-relative ("//evil.com") and any scheme-prefixed shenanigans early.
        if (str_starts_with($candidate, '//') || str_starts_with($candidate, '\\')) {
            return null;
        }

        $parts = parse_url($candidate);

        if ($parts === false) {
            return null;
        }

        // If a host is present it must match the app host (no external redirects).
        if (! empty($parts['host'])) {
            if (! empty($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                return null;
            }
            if (strcasecmp($parts['host'], $request->getHost()) !== 0) {
                return null;
            }
        }

        $path = $parts['path'] ?? '/';

        // Must be an absolute path on this site.
        if (! str_starts_with($path, '/')) {
            return null;
        }

        $local = $path;
        if (! empty($parts['query'])) {
            $local .= '?' . $parts['query'];
        }

        return $local;
    }
}
