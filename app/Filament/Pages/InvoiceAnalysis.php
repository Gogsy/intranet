<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\InvoiceTracker;
use App\Filament\InvoiceTracker\Widgets\Analysis\AnalysisStats;
use App\Filament\InvoiceTracker\Widgets\Analysis\CategoryDetailTable;
use App\Filament\InvoiceTracker\Widgets\Analysis\CategorySpendChart;
use App\Filament\InvoiceTracker\Widgets\Analysis\MonthlySpendTrend;
use App\Filament\InvoiceTracker\Widgets\Analysis\TopSuppliersChart;
use App\Filament\InvoiceTracker\Widgets\Analysis\TopSuppliersTable;
use App\Models\Invoice;
use App\Models\SupplierBudget;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/** Invoice Tracker analysis: charts and detail tables for the selected year. */
class InvoiceAnalysis extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'analysis';

    protected static ?string $title = 'Analysis';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $cluster = InvoiceTracker::class;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('year')
                    ->options(function (): array {
                        $years = Invoice::query()->distinct()->pluck('year')
                            ->merge(SupplierBudget::query()->distinct()->pluck('year'))
                            ->push(now()->year)
                            ->unique()
                            ->sortDesc()
                            ->values();

                        return $years->combine($years)->all();
                    })
                    ->default(now()->year)
                    ->selectablePlaceholder(false),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            AnalysisStats::class,
            TopSuppliersChart::class,
            CategorySpendChart::class,
            MonthlySpendTrend::class,
            TopSuppliersTable::class,
            CategoryDetailTable::class,
        ];
    }
}
