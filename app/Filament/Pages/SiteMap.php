<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;

class SiteMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Site Map';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.pages.site-map';

    public static function canAccess(): bool
    {
        // Navigation aid for any panel user. Filament already gates panel entry.
        return auth()->check();
    }

    /**
     * Curated list of public front-end sites.
     *
     * @return array<int, array{label: string, url: string, description: string}>
     */
    public function getLinks(): array
    {
        $defs = [
            ['label' => 'Home',                 'route' => null,           'fallback' => '/', 'description' => 'Public landing page.'],
            ['label' => 'Web Tools',            'route' => 'tools.index',  'description' => 'Browser-based utilities and tools.'],
            ['label' => 'App Downloads',        'route' => 'apps.index',   'description' => 'Downloadable desktop / mobile applications.'],
            ['label' => 'Documentation Portal', 'route' => 'docs.index',   'description' => 'Knowledge base and documentation.'],
            ['label' => 'Imenik (Phonebook)',   'route' => 'imenik.index', 'description' => 'Internal phone directory.'],
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
                } catch (\Throwable $e) {
                    $url = null;
                }
            }

            if ($url === null) {
                continue;
            }

            $links[] = [
                'label'       => $def['label'],
                'url'         => $url,
                'description' => $def['description'] ?? '',
            ];
        }

        return $links;
    }

    /**
     * All registered GET web routes, for a complete site map.
     *
     * @return array<int, array{uri: string, name: ?string}>
     */
    public function getAllGetRoutes(): array
    {
        $skipPrefixes = ['admin', 'livewire', '_ignition', '_debugbar', 'storage'];
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
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
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

            $routes[] = [
                'uri'  => $normalized,
                'name' => $route->getName(),
            ];
        }

        usort($routes, fn ($a, $b) => strcmp($a['uri'], $b['uri']));

        return $routes;
    }
}
