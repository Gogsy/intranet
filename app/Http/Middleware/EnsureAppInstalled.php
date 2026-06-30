<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-run guard.
 *
 * While the application has not been installed (AppSetting::current()->setup_completed_at
 * is null) every request is funneled to the install wizard, EXCEPT the wizard routes
 * themselves and static asset / livewire endpoints (otherwise the wizard could not load).
 *
 * Once installed, the wizard is locked: any request to /install* is bounced to '/'.
 *
 * Decision: while not installed we force the wizard first and redirect EVERYTHING that
 * isn't the wizard or an asset/livewire route — including /admin and /login — because
 * there is no admin account yet, so login/admin would be useless anyway.
 */
class EnsureAppInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = AppSetting::current()->setup_completed_at !== null;

        // Always-allowed paths so the wizard, its POSTs, assets and livewire keep working.
        $allowed = $request->is(
            'install',
            'install/*',
            // Static assets & framework endpoints the wizard pages depend on.
            'livewire/*',
            'css/*',
            'js/*',
            'images/*',
            'fonts/*',
            'storage/*',
            'build/*',
            'favicon.ico',
            'robots.txt',
            'up',           // health check
        );

        if (! $installed) {
            // Not installed: let through allowed paths, otherwise send to the wizard.
            if ($allowed) {
                return $next($request);
            }

            return redirect()->route('install.index');
        }

        // Installed: the wizard is locked — bounce any /install* request home.
        if ($request->is('install', 'install/*')) {
            return redirect('/');
        }

        return $next($request);
    }
}
