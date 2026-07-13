<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Support\InvoiceTracker\YearOverview;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class CategoryDetailTable extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.invoice-tracker.widgets.category-detail';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -5;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    protected function getViewData(): array
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);

        return YearOverview::categoryDetail($year);
    }
}
