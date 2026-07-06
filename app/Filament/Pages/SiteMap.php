<?php

namespace App\Filament\Pages;

use Throwable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;

class SiteMap extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Site Map';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.site-map';

    public static function canAccess(): bool
    {
        // Navigation aid for any panel user. Filament already gates panel entry.
        return auth()->check();
    }

    public function getSubheading(): ?string
    {
        return 'Overview of the public front-end pages and every registered route.';
    }

    /**
     * Curated list of public front-end sites.
     *
     * @return array<int, array{label: string, url: string, description: string, icon: string}>
     */
    public function getLinks(): array
    {
        $defs = [
            ['label' => 'Home',                 'route' => null,           'fallback' => '/', 'icon' => 'heroicon-o-home',              'description' => 'Public landing page.'],
            ['label' => 'Web Tools',            'route' => 'tools.index',  'icon' => 'heroicon-o-wrench-screwdriver',                   'description' => 'Browser-based utilities and tools.'],
            ['label' => 'App Downloads',        'route' => 'apps.index',   'icon' => 'heroicon-o-arrow-down-tray',                      'description' => 'Downloadable desktop / mobile applications.'],
            ['label' => 'Documentation Portal', 'route' => 'docs.index',   'icon' => 'heroicon-o-book-open',                            'description' => 'Knowledge base and documentation.'],
            ['label' => 'Imenik (Phonebook)',   'route' => 'imenik.index', 'icon' => 'heroicon-o-phone',                                'description' => 'Internal phone directory.'],
        ];

        $links = [];

        foreach ($defs as $def) {
            $url = null;

            if (empty($def['route'])) {
                $url = url($def['fallback'] ?? '/');
            } else {
                try {
                    if (Route::has($def['route'])) {
                        $url = route($def['route']);
                    }
                } catch (Throwable) {
                    $url = null;
                }
            }

            if ($url === null) {
                continue;
            }

            $links[] = [
                'label'       => $def['label'],
                'url'         => $url,
                'icon'        => $def['icon'] ?? 'heroicon-o-link',
                'description' => $def['description'] ?? '',
            ];
        }

        return $links;
    }

    /**
     * All registered GET web routes, for a complete site map.
     *
     * @return array<int, array{uri: string, name: ?string, access: string}>
     */
    public function getAllGetRoutes(): array
    {
        // "install" is the one-time setup wizard — dead links once the app is installed.
        // "livewire" also matches the hashed asset prefix (e.g. livewire-807a745d).
        $skipPrefixes = ['admin', 'livewire', '_ignition', '_debugbar', 'storage', 'install', 'filament', 'up'];
        $routes = [];
        $seen = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if ($uri === null || $uri === '') {
                continue;
            }

            // Skip ignored prefixes.
            $skip = false;
            foreach ($skipPrefixes as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/') || str_starts_with($uri, $prefix . '-')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Skip parameterised routes we can't resolve to a plain link.
            if (str_contains($uri, '{')) {
                continue;
            }

            $normalized = '/' . ltrim($uri, '/');

            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            $middleware = $route->gatherMiddleware();
            $access = 'public';
            if (in_array('auth', $middleware, true)) {
                $access = 'auth';
            } elseif (in_array('guest', $middleware, true)) {
                $access = 'guest';
            }

            $routes[] = [
                'uri'    => $normalized,
                'name'   => $route->getName(),
                'access' => $access,
            ];
        }

        usort($routes, fn ($a, $b) => strcmp($a['uri'], $b['uri']));

        return $routes;
    }
}
