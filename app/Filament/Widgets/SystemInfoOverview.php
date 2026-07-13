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
    // Skroz na dnu dashboarda — ispod svih poslovnih widgeta.
    protected static ?int $sort = 100;

    protected ?string $heading = 'System';

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
                ->icon('heroicon-o-cube')
                ->color('primary'),
            Stat::make('Laravel', app()->version())
                ->description('Framework')
                ->icon('heroicon-o-code-bracket')
                ->color('gray'),
            Stat::make('PHP', PHP_VERSION)
                ->description('Runtime')
                ->icon('heroicon-o-command-line')
                ->color('gray'),
            Stat::make('Database', $this->databaseVersion())
                ->description(DB::connection()->getDriverName())
                ->icon('heroicon-o-circle-stack')
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
