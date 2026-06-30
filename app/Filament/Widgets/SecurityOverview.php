<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

class SecurityOverview extends BaseWidget
{
    protected static ?int $sort = -3;

    /** Super admins only. */
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        return [
            Stat::make('Logins today', Activity::where('event', 'login')->where('created_at', '>=', $today)->count())
                ->description('Successful sign-ins')
                ->color('success'),
            Stat::make('Failed logins today', Activity::where('event', 'failed')->where('created_at', '>=', $today)->count())
                ->description('Wrong credentials')
                ->color('danger'),
            Stat::make('Changes today', Activity::whereIn('event', ['created', 'updated', 'deleted'])->where('created_at', '>=', $today)->count())
                ->description('Records added/edited/removed')
                ->color('warning'),
            Stat::make('Total logged events', Activity::count())
                ->description('All time')
                ->color('gray'),
        ];
    }
}
