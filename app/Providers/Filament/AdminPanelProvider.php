<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Filament\Navigation\NavigationGroup;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->passwordReset()
            // Profile page (top-right menu) — where each user manages MFA below.
            ->profile()
            // Built-in multi-factor auth: authenticator app (TOTP, with
            // recovery codes) or one-time codes by email. Every user may
            // enable it on their profile; a super admin can additionally
            // REQUIRE it per user (User form → mfa_required). isRequired is
            // true only so the setup route + guard are registered — the
            // actual per-user enforcement lives in EnsureMfaForFlaggedUsers
            // (registered below), which lets un-flagged users pass through.
            ->multiFactorAuthentication([
                \Filament\Auth\MultiFactor\App\AppAuthentication::make()->recoverable(),
                \Filament\Auth\MultiFactor\Email\EmailAuthentication::make(),
            ], isRequired: true)
            ->multiFactorAuthenticationRequiredMiddlewareName(
                \App\Http\Middleware\EnsureMfaForFlaggedUsers::class,
            )
            ->globalSearch(false)
            ->colors($this->brandColors())
            ->favicon(fn () => $this->branding()?->faviconUrl ?? asset('images/favicon-32x32.png'))
            ->brandLogo(fn () => $this->branding()?->logoUrl ?? asset('images/logo_text.svg'))
            ->brandLogoHeight(fn () => ($this->branding()?->adminLogoHeight ?? AppSetting::DEFAULT_ADMIN_LOGO_HEIGHT) . 'px')

            // Početna stranica: dashboard (ne Users — ta lista traži
            // view_users, koju ograničene role, npr. docs_manager, nemaju;
            // slali bi ih ravno u 403 odmah nakon logina).
            ->homeUrl(fn () => route('filament.admin.pages.dashboard'))

            // Otkrij sve resurse
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            // Filament's stock dashboard: account + Filament version widgets,
            // plus our SecurityOverview and SystemInfoOverview from discovery.
            ->pages([
                \Filament\Pages\Dashboard::class,
            ])
            ->widgets([
                \Filament\Widgets\AccountWidget::class,
                \Filament\Widgets\FilamentInfoWidget::class,
            ])

            ->plugins([
                // Roles sits in Administration right below Users (Users = 10).
                FilamentShieldPlugin::make()
                    ->navigationGroup('Administration')
                    ->navigationSort(11),

                // Login/logout/failed-login trail. Registering the plugin is
                // required so filament('authentication-log') resolves (the
                // resource references it when rendering rows). The resource is
                // placed in the Security group via config and access-gated by
                // App\Policies\AuthenticationLogPolicy (view_security).
                \Tapp\FilamentAuthenticationLog\FilamentAuthenticationLogPlugin::make(),

                // File Manager, storage mode over the 'public' disk: browse
                // EVERY upload with full delete/rename/upload. Only the one
                // page is registered (the read-only File System twin, the demo
                // Schema Example and the local Embed test are left out). Nav
                // sits in Administration (not Security); access is gated by
                // the `manage_files` permission (config/filemanager.php
                // authorization), held by admin + super_admin.
                \MWGuerra\FileManager\FileManagerPlugin::make([
                    \MWGuerra\FileManager\Filament\Pages\FileManager::class,
                ])->fileManagerNavigation(
                    icon: 'heroicon-o-folder',
                    label: 'File Manager',
                    sort: 12,
                    group: 'Administration',
                ),
            ])

            // Lets the sidebar collapse to icon-only on desktop, freeing up
            // horizontal space for wide grids (e.g. Budget Planner's 12-month tables).
            ->sidebarCollapsibleOnDesktop()

            // Row-status colors for the Budget Planner investment grid
            // (see InvestmentItemsRelationManager::recordClasses()). rgba
            // overlays work in both light and dark theme.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => new HtmlString('<style>
                    .bp-row-approved { background-color: rgba(34, 197, 94, .10) !important; }
                    .bp-row-rejected { background-color: rgba(239, 68, 68, .10) !important; }
                    .bp-row-deferred { opacity: .55; }
                    .bp-row-warning { background-color: rgba(239, 68, 68, .25) !important; }
                    /* Compact 12-month grid on the Budget Planner expenses tab.
                       Filament\'s TextInputColumn/SelectColumn hardcode
                       min-w-48 (192px) per cell — 12 month columns at that
                       width is what forced the horizontal scrollbar, so the
                       overrides below are load-bearing, not cosmetic. */
                    .bp-month-input { min-width: 0 !important; width: 4.25rem; padding: 0 !important; }
                    .bp-month-input input {
                        text-align: right; font-size: .75rem; padding: .3rem .3rem;
                        font-variant-numeric: tabular-nums; -moz-appearance: textfield;
                    }
                    .bp-month-input input::-webkit-outer-spin-button,
                    .bp-month-input input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
                    /* Disabled inputs swallow mouse events — let them fall
                       through to the wrapper so right-click marking works on
                       locked versions (mark colours: planner-tools.blade.php). */
                    .bp-month-input input:disabled { pointer-events: none; }
                    /* Wide screens stretch the columns beyond the fixed-width
                       input/select boxes — centre each box in its cell so it
                       lines up with its (alignCenter) header label above. */
                    .bp-month-input, .bp-type-select, .bp-narrow-select, .bp-num-input { margin-inline: auto; }
                    /* Vertical separators between EVERY column of the compact
                       grids (header + body), Excel-style, so each month/field
                       is clearly delimited. */
                    .fi-ta-table:has(tr.bp-compact) thead th:not(:first-of-type),
                    .fi-ta-table:has(tr.bp-compact) tr.bp-compact > td:not(:first-of-type) {
                        border-inline-start: 2px solid rgba(128, 128, 128, .3);
                    }
                    .bp-type-select { min-width: 0 !important; width: 8rem; padding: 0 !important; }
                    .bp-type-select select { font-size: .75rem; padding-block: .3rem; }
                    /* Narrow month select (investments) and numeric qty/price inputs. */
                    .bp-narrow-select { min-width: 0 !important; width: 4.7rem; padding: 0 !important; }
                    .bp-narrow-select select { font-size: .75rem; padding-block: .3rem; }
                    .bp-num-input { min-width: 0 !important; width: 5.2rem; padding: 0 !important; }
                    .bp-num-input input {
                        text-align: right; font-size: .75rem; padding: .3rem .3rem;
                        font-variant-numeric: tabular-nums; -moz-appearance: textfield;
                    }
                    .bp-num-input input::-webkit-outer-spin-button,
                    .bp-num-input input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
                    /* Tight cells, shorter rows and a smaller font on compact tables.
                       Inline column wrappers (text input, select, icon, checkbox,
                       toggle) all carry a built-in px-3 py-4 (24px/32px) — that
                       inset is what pushed inputs away from their headers, so it
                       is collapsed here and the td padding becomes the only gap. */
                    tr.bp-compact > td { padding-inline: .2rem; vertical-align: middle; }
                    tr.bp-compact > td:first-of-type { padding-inline-start: .75rem; }
                    /* v5 dropped Tailwind utilities from table markup (.text-sm is
                       gone), so the size lands directly on the cell wrapper. */
                    tr.bp-compact .fi-ta-text { padding-block: .3rem; padding-inline: .4rem; font-size: .75rem; }
                    tr.bp-compact input, tr.bp-compact select { font-size: .75rem; }
                    tr.bp-compact .fi-ta-text-input,
                    tr.bp-compact .fi-ta-select,
                    tr.bp-compact .fi-ta-icon,
                    tr.bp-compact .fi-ta-checkbox,
                    tr.bp-compact .fi-ta-toggle { padding: .15rem .2rem !important; }
                    tr.bp-compact .fi-ta-text-input input { padding-block: .3rem; padding-inline: .4rem; }
                    tr.bp-compact .fi-ta-select select { padding-block: .3rem; }
                    /* Slim column headers, aligned with the cells below them. */
                    .fi-ta-table:has(tr.bp-compact) thead th { padding-inline: .4rem; padding-block: .5rem; }
                    .fi-ta-table:has(tr.bp-compact) thead th:first-of-type { padding-inline-start: .95rem; }
                    /* v5 renamed the header label: sortable columns wrap it in
                       .fi-ta-header-cell-sort-btn, plain ones inline in the th. */
                    .fi-ta-table:has(tr.bp-compact) thead th,
                    .fi-ta-table:has(tr.bp-compact) thead th .fi-ta-header-cell-sort-btn { font-size: .75rem; }
                    /* Icon row-actions: trim the gap so three icons cost little width. */
                    tr.bp-compact .fi-ta-actions { gap: .125rem; padding-inline: .25rem; }
                    /* Budget Planner tabs: collapse the two-row table header
                       (heading row + search/filter toolbar row) into ONE row:
                       [heading] [search + filter, centered] [Toggle select | Add …].
                       Scoped via the bp-one-row-header marker class carried by
                       the "Toggle select" header button. */
                    .fi-ta-header-ctn:has(.bp-one-row-header) {
                        display: flex; flex-wrap: wrap; align-items: center;
                        column-gap: 1rem; padding-inline: 1rem; padding-block: .5rem;
                        /* One full-width divider under the whole header strip —
                           without it only the middle (toolbar) segment drew a
                           border, so the line seemed to vanish under the
                           heading and the action buttons. */
                        border-bottom: 1px solid rgba(128, 128, 128, .3);
                    }
                    .fi-ta-header-ctn:has(.bp-one-row-header) > * {
                        border-top-width: 0 !important; border-bottom-width: 0 !important;
                    }
                    .fi-ta-header-ctn:has(.bp-one-row-header) > .fi-ta-header { display: contents; }
                    .fi-ta-header-ctn:has(.bp-one-row-header) .fi-ta-header > *:not(.fi-ta-actions) { order: 1; }
                    .fi-ta-header-ctn:has(.bp-one-row-header) > .fi-ta-header-toolbar { order: 2; flex: 1 1 auto; padding-inline: 0; }
                    .fi-ta-header-ctn:has(.bp-one-row-header) .fi-ta-header > .fi-ta-actions { order: 3; }
                    /* Center the search + filter cluster between heading and buttons.
                       In v5 the cluster is the toolbar\'s plain <div> child (the
                       .ms-auto utility from v3 is gone from core markup). */
                    .fi-ta-header-ctn:has(.bp-one-row-header) .fi-ta-header-toolbar > div:not(.fi-ta-actions) { margin-inline: auto; }
                </style>'),
            )

            // Floating calculator + Alt+click expense marking (Budget Planner).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.budget.planner-tools'),
            )

            // Live-presence container in the top bar (right, next to the user
            // avatar). planner-tools.blade.php's presence JS renders the "who
            // else is here" pills into this; it stays empty off the Budget
            // Planner, so it's invisible everywhere else.
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => new HtmlString('<div id="bp-presence-topbar" class="bp-presence-topbar"></div>'),
            )

            // Group icons are intentionally omitted: Filament does not allow a group
            // to have an icon when its items also have icons (and our items do).
            // Order here = order in the sidebar (top → bottom).
            ->navigationGroups([
                NavigationGroup::make('Administration'),
                NavigationGroup::make('IT Budget'),
                NavigationGroup::make('Applications'),
                NavigationGroup::make('Security'),
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
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
    protected function branding(): ?AppSetting
    {
        try {
            if (Schema::hasTable('app_settings')) {
                return AppSetting::current();
            }
        } catch (Throwable) {
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
            'primary' => $primary ? Color::generateV3Palette($primary) : Color::Amber,
        ];
    }
}
