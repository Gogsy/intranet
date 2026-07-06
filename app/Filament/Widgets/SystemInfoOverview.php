<?php

namespace App\Filament\Widgets;

use Composer\InstalledVersions;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class SystemInfoOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    /** Same gate as the settings pages. */
    public static function canView(): bool
    {
        return auth()->user()?->can('manage_settings') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Application', 'v' . config('app.version'))
                ->description(config('app.name') . ' — ' . app()->environment())
                ->color('primary'),
            Stat::make('Laravel', app()->version())
                ->description('Framework')
                ->color('gray'),
            Stat::make('PHP', PHP_VERSION)
                ->description('Runtime')
                ->color('gray'),
            Stat::make('Database', $this->databaseVersion())
                ->description(DB::connection()->getDriverName())
                ->color('gray'),
        ];
    }

    protected function databaseVersion(): string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            return 'unavailable';
        }
    }
}
