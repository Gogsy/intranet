<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Models\Invoice;
use App\Models\SupplierCategory;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CategorySpendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -8;

    protected ?string $maxHeight = '320px';

    protected const PALETTE = [
        '#F58220', '#0EA5E9', '#22C55E', '#A855F7', '#EF4444', '#EAB308', '#14B8A6', '#64748B',
    ];

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function getHeading(): string
    {
        return 'Spend by category — '.$this->getYear();
    }

    protected function getData(): array
    {
        $totals = Invoice::query()
            ->forYear($this->getYear())
            ->selectRaw('category_id, SUM(amount) AS total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $categories = SupplierCategory::query()
            ->whereIn('id', $totals->pluck('category_id')->filter())
            ->with('supplier')
            ->get()
            ->keyBy('id');

        $labeled = $totals->map(fn ($row): array => [
            'label' => $row->category_id
                ? ($categories[$row->category_id]->supplier->name.' — '.$categories[$row->category_id]->name)
                : 'Uncategorized',
            'total' => round((float) $row->total, 2),
        ]);

        $top = $labeled->take(7);

        if ($labeled->count() > 7) {
            $top->push([
                'label' => 'Other',
                'total' => round($labeled->slice(7)->sum('total'), 2),
            ]);
        }

        return [
            'labels' => $top->pluck('label')->all(),
            'datasets' => [
                [
                    'data' => $top->pluck('total')->all(),
                    'backgroundColor' => array_slice(self::PALETTE, 0, $top->count()),
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getYear(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }
}
