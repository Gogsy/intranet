<?php

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PhoneNumber;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PhoneBookOverview extends BaseWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Phone Book';

    /** Dashboard stats aren't live data — Filament's default 5s poll was pure overhead. */
    protected ?string $pollingInterval = null;

    /** Same gate as the Phone Book module — super_admin (bypass) + admin, who both hold view_phone_book. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_phone_book') ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Zaposleni u imeniku', Employee::count())
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Brojevi telefona', PhoneNumber::count())
                ->description(PhoneNumber::free()->count() . ' slobodnih')
                ->icon('heroicon-o-phone')
                ->color('info'),
            Stat::make('Odjeli', Department::count())
                ->icon('heroicon-o-building-office-2')
                ->color('gray'),
            Stat::make('Javno vidljivi brojevi', PhoneNumber::public()->count())
                ->description('Vidljivi na /imenik')
                ->icon('heroicon-o-globe-alt')
                ->color('gray'),
        ];
    }
}
