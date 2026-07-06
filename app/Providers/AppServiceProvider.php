<?php

namespace App\Providers;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Table;
use Illuminate\Support\Facades\View;
use App\Models\AppSetting;
use Throwable;
use Illuminate\Support\Facades\Schema;
use App\Models\MailSetting;
use Spatie\Activitylog\Models\Activity;
use App\Services\ActivityLogCompactor;
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
            LoginResponse::class,
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

        // Filament v4 defers table filters behind an "Apply" click; keep the
        // pre-upgrade instant filtering everywhere.
        Table::configureUsing(fn (Table $table) => $table->deferFilters(false));

        // Store every upload under its original client filename instead of a
        // random hash. Note: a same-named file uploaded to the same directory
        // replaces the existing one.
        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->preserveFilenames());

        // Share branding settings with every view as $branding. Resolved
        // lazily per render (not at boot) so it also works when the
        // app_settings table appears only after boot — e.g. tests migrating
        // a fresh database, or the initial installer run.
        View::composer('*', function ($view) {
            if (array_key_exists('branding', $view->getData())) {
                return;
            }

            try {
                $view->with('branding', AppSetting::current());
            } catch (Throwable $e) {
                // No DB / table yet (mid-migration): fall back to defaults.
                $view->with('branding', new AppSetting());
            }
        });

        // Apply DB-stored SMTP settings (Mail Settings page) over the .env defaults.
        try {
            if (Schema::hasTable('mail_settings')) {
                optional(MailSetting::first())->apply();
            }
        } catch (Throwable $e) {
            // ignore (e.g. during migration / no DB)
        }

        // Auth events (login/logout/failed) are logged in EventServiceProvider —
        // do not add listeners here too or every login gets recorded twice.

        // Budget Planner change log: fold rapid-fire inline-grid edits of the
        // same row by the same user into one activity (net change per session).
        Activity::created(
            fn (Activity $activity) => ActivityLogCompactor::compact($activity),
        );

        // Remove uploaded files when records are deleted.
        Application::observe(ApplicationObserver::class);
        ApplicationVersion::observe(ApplicationVersionObserver::class);
        Tool::observe(ToolObserver::class);
        DocNode::observe(DocNodeObserver::class);
        DocAttachment::observe(DocAttachmentObserver::class);
    }
}
