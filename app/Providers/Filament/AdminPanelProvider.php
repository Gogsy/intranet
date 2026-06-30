<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors($this->brandColors())
            ->favicon(fn () => $this->branding()?->faviconUrl ?? asset('images/favicon-32x32.png'))
            ->brandLogo(fn () => $this->branding()?->logoUrl ?? asset('images/logo_text.svg'))

            // Početna stranica ide direktno na users
            ->homeUrl(fn () => route('filament.admin.resources.users.index'))

            // Otkrij sve resurse
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
            ])

            // Group icons are intentionally omitted: Filament does not allow a group
            // to have an icon when its items also have icons (and our items do).
            // Order here = order in the sidebar (top → bottom).
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Administration'),
                \Filament\Navigation\NavigationGroup::make('Applications'),
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Branding singleton, guarded so the panel still boots before the
     * app_settings table exists (e.g. during migrations / no DB).
     */
    protected function branding(): ?\App\Models\AppSetting
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('app_settings')) {
                return \App\Models\AppSetting::current();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    /**
     * Build the colour palette, using the configured primary hex when present.
     */
    protected function brandColors(): array
    {
        $primary = $this->branding()?->primary_color;

        return [
            'primary' => $primary ? Color::hex($primary) : Color::Amber,
        ];
    }
}
