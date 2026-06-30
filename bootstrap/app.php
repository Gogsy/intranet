<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // First-run guard: until the app is installed, force the install wizard.
        // Registered as GLOBAL (prepended) so it also covers the Filament /admin panel,
        // which uses its own middleware stack rather than the `web` group.
        $middleware->prepend(\App\Http\Middleware\EnsureAppInstalled::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
