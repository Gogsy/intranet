<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Models\Invoice;
use App\Models\Supplier;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopSuppliersChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -9;

    protected ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function getHeading(): string
    {
        return 'Top suppliers — '.$this->getYear();
    }

    protected function getData(): array
    {
        $totals = Invoice::query()
            ->forYear($this->getYear())
            ->selectRaw('supplier_id, SUM(amount) AS total')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $names = Supplier::query()
            ->whereIn('id', $totals->pluck('supplier_id'))
            ->pluck('name', 'id');

        return [
            'labels' => $totals->map(fn ($row) => $names[$row->supplier_id] ?? '?')->all(),
            'datasets' => [
                [
                    'label' => 'Spent (EUR)',
                    'data' => $totals->map(fn ($row) => round((float) $row->total, 2))->all(),
                    'backgroundColor' => '#F58220',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getYear(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }
}
