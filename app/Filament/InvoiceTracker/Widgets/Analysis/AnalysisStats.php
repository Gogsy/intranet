<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Models\Invoice;
use App\Support\InvoiceTracker\YearOverview;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AnalysisStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    protected function getStats(): array
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);

        $total = (float) Invoice::query()->forYear($year)->sum('amount');

        $topSupplier = Invoice::query()
            ->forYear($year)
            ->selectRaw('supplier_id, SUM(amount) AS total')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->with('supplier')
            ->first();

        $uncategorized = (float) Invoice::query()
            ->forYear($year)
            ->whereNull('category_id')
            ->sum('amount');

        $overBudgetMonths = collect(YearOverview::matrix($year)['rows'])
            ->sum(fn (array $row): int => collect($row['cells'])->where('over', true)->count());

        return [
            Stat::make("Total spent in {$year}", Number::currency($total, in: 'EUR'))
                ->icon('heroicon-o-banknotes'),
            Stat::make('Top supplier', $topSupplier?->supplier?->name ?? '—')
                ->description($topSupplier
                    ? Number::currency((float) $topSupplier->total, in: 'EUR').($total > 0 ? ' · '.number_format((float) $topSupplier->total / $total * 100, 0).'% of total' : '')
                    : 'No entries yet')
                ->icon('heroicon-o-trophy'),
            Stat::make('Over-budget months', $overBudgetMonths)
                ->description('Supplier-months above their monthly budget')
                ->color($overBudgetMonths > 0 ? 'warning' : 'success')
                ->icon($overBudgetMonths > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
            Stat::make('Ad hoc (uncategorized)', Number::currency($uncategorized, in: 'EUR'))
                ->description($total > 0 ? number_format($uncategorized / $total * 100, 1).'% of total' : null)
                ->icon('heroicon-o-question-mark-circle'),
        ];
    }
}
