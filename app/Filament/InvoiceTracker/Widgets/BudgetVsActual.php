<?php

namespace App\Filament\InvoiceTracker\Widgets;

use App\Support\InvoiceTracker\YearOverview;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class BudgetVsActual extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.invoice-tracker.widgets.budget-vs-actual';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -9;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    protected function getViewData(): array
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);

        return [
            'year' => $year,
            'rows' => YearOverview::budgetVsActual($year),
        ];
    }
}
