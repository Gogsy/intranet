<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Records authentication events (login, logout, failed login) into the activity
 * log so they appear alongside model changes in the Activity Log resource.
 *
 * Model create/update/delete events are captured by the LogsModelActivity trait;
 * auth events are not Eloquent events, so they are logged here explicitly.
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            activity('auth')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->event('login')
                ->log('login');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            activity('auth')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip(), 'guard' => $event->guard])
                ->event('logout')
                ->log('logout');
        });

        Event::listen(Failed::class, function (Failed $event): void {
            activity('auth')
                ->withProperties([
                    'ip' => request()->ip(),
                    'guard' => $event->guard,
                    'email' => $event->credentials['email'] ?? null,
                ])
                ->event('failed')
                ->log('failed login');
        });
    }
}
