<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Interface\ApplicationRepositoryInterface;
use App\Repository\ApplicationRepository;
use App\Models\Application;
use App\Models\ApplicationVersion;
use App\Models\Tool;
use App\Models\DocNode;
use App\Models\DocAttachment;
use App\Observers\ApplicationObserver;
use App\Observers\ApplicationVersionObserver;
use App\Observers\ToolObserver;
use App\Observers\DocNodeObserver;
use App\Observers\DocAttachmentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensure the branding() helper is available even before a
        // `composer dump-autoload` registers the autoload "files" entry.
        require_once app_path('Support/helpers.php');

        // Repository binding
        $this->app->singleton(ApplicationRepositoryInterface::class, ApplicationRepository::class);

        // Always redirect admins to the panel home after login (never /pulse).
        $this->app->bind(
            \Filament\Http\Responses\Auth\Contracts\LoginResponse::class,
            \App\Http\Responses\Auth\LoginResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Share branding settings with every view as $branding (guarded so the
        // app still boots before the app_settings table exists / migrates).
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) {
                \Illuminate\Support\Facades\View::share('branding', \App\Models\AppSetting::current());
            }
        } catch (\Throwable $e) {
            // ignore (e.g. during migration / no DB)
        }

        // Apply DB-stored SMTP settings (Mail Settings page) over the .env defaults.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('mail_settings')) {
                optional(\App\Models\MailSetting::first())->apply();
            }
        } catch (\Throwable $e) {
            // ignore (e.g. during migration / no DB)
        }

        // Security monitoring: log authentication events (who/when/where).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($e) {
            activity('auth')->causedBy($e->user)
                ->withProperties(['ip' => request()->ip(), 'agent' => request()->userAgent()])
                ->event('login')->log('Logged in');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($e) {
            activity('auth')->causedBy($e->user)
                ->withProperties(['ip' => request()->ip()])
                ->event('logout')->log('Logged out');
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function ($e) {
            activity('auth')
                ->withProperties(['email' => $e->credentials['email'] ?? null, 'ip' => request()->ip()])
                ->event('failed')->log('Failed login');
        });

        // Remove uploaded files when records are deleted.
        Application::observe(ApplicationObserver::class);
        ApplicationVersion::observe(ApplicationVersionObserver::class);
        Tool::observe(ToolObserver::class);
        DocNode::observe(DocNodeObserver::class);
        DocAttachment::observe(DocAttachmentObserver::class);
    }
}
