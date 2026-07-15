<?php

namespace App\Filament\Widgets;

use App\Models\BudgetVersion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BudgetOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'IT Budget';

    /** Dashboard stats aren't live data — Filament's default 5s poll was pure overhead. */
    protected ?string $pollingInterval = null;

    /** Same gate as the Budget Planner itself — super_admin (bypass) + admin, who both hold view_budget. */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_budget') ?? false;
    }

    /** Exactly 2 stats — force them to split the row 50/50 instead of the default 3/4-column grid. */
    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $activeCount = BudgetVersion::whereIn('status', ['DRAFT', 'TEMPORARILY_UNLOCKED'])->count();
        $lockedCount = BudgetVersion::where('status', 'LOCKED')->count();
        $total = $activeCount + $lockedCount;
        $lockedPercent = $total > 0 ? round($lockedCount / $total * 100) : 0;

        return [
            Stat::make('Aktivni budžeti', $activeCount)
                ->description('U izradi ili privremeno otključani')
                ->icon('heroicon-o-briefcase')
                ->color('warning'),
            Stat::make('Zaključano / Otvoreno', $lockedCount . ' / ' . $activeCount)
                ->description($lockedPercent . '% budžeta je zaključano')
                ->icon('heroicon-o-scale')
                ->color('gray'),
        ];
    }
}
