<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Models\Invoice;
use App\Models\SupplierBudget;
use App\Support\InvoiceTracker\Months;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlySpendTrend extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '340px';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function getHeading(): string
    {
        return 'Monthly spend vs. budget — '.$this->getYear();
    }

    protected function getData(): array
    {
        $year = $this->getYear();

        $spent = Invoice::query()
            ->forYear($year)
            ->visibleInOverview()
            ->selectRaw('month, SUM(amount) AS total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $budget = SupplierBudget::query()
            ->forYear($year)
            ->visibleInOverview()
            ->selectRaw('month, SUM(amount) AS total')
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => array_map(fn (int $m) => Months::shortName($m), range(1, 12)),
            'datasets' => [
                [
                    'label' => 'Spent (EUR)',
                    'data' => array_map(fn (int $m) => round((float) ($spent[$m] ?? 0), 2), range(1, 12)),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, .15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Budget (EUR)',
                    'data' => array_map(fn (int $m) => round((float) ($budget[$m] ?? 0), 2), range(1, 12)),
                    'borderColor' => '#94A3B8',
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getYear(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }
}
