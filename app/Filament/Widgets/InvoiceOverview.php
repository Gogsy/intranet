<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Supplier;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Invoices';

    /** Dashboard stats aren't live data — Filament's default 5s poll was pure overhead. */
    protected ?string $pollingInterval = null;

    /** Super Admin only — Administrator (admin role) does not see this overview. */
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getStats(): array
    {
        $year = now()->year;
        $month = now()->month;

        return [
            Stat::make('Fakture ovaj mjesec', Invoice::forMonth($year, $month)->count())
                ->description(now()->translatedFormat('F Y'))
                ->icon('heroicon-o-document-text')
                ->color('info'),
            Stat::make('Iznos ovaj mjesec', number_format((float) Invoice::forMonth($year, $month)->sum('amount'), 2) . ' €')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),
            Stat::make('Iznos ova godina', number_format((float) Invoice::forYear($year)->sum('amount'), 2) . ' €')
                ->description((string) $year)
                ->icon('heroicon-o-calendar')
                ->color('primary'),
            Stat::make('Aktivni dobavljači', Supplier::active()->count())
                ->icon('heroicon-o-building-office')
                ->color('success'),
        ];
    }
}
