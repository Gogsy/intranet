<?php

namespace App\Filament\InvoiceTracker\Widgets;

use App\Support\InvoiceTracker\YearOverview;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * NOTE: Invoice Tracker widgets live outside app/Filament/Widgets on purpose —
 * that directory is panel-discovered and everything in it lands on the stock
 * dashboard. These are registered only by the two Invoice Tracker pages.
 */
class SupplierMonthMatrix extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.invoice-tracker.widgets.supplier-month-matrix';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    protected function getViewData(): array
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);

        return YearOverview::matrix($year);
    }
}
