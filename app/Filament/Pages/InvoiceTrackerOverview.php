<?php

namespace App\Filament\Pages;

use App\Exports\InvoiceTracker\BudgetVsActualExport;
use App\Exports\InvoiceTracker\SupplierMonthMatrixExport;
use App\Filament\Clusters\InvoiceTracker;
use App\Filament\InvoiceTracker\Widgets\BudgetVsActual;
use App\Filament\InvoiceTracker\Widgets\SupplierMonthMatrix;
use App\Models\Invoice;
use App\Models\SupplierBudget;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Invoice Tracker home: suppliers × months matrix + budget-vs-actual for the
 * selected year. A Dashboard subclass (for HasFiltersForm + widget grid) but
 * NOT the panel's default dashboard — it lives in the InvoiceTracker cluster
 * under its own route.
 */
class InvoiceTrackerOverview extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'overview';

    protected static ?string $title = 'Invoice Tracker';

    protected static ?string $navigationLabel = 'Overview';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $cluster = InvoiceTracker::class;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function getWidgets(): array
    {
        return [
            SupplierMonthMatrix::class,
            BudgetVsActual::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportMatrix')
                ->label('Export matrix')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $year = (int) ($this->filters['year'] ?? now()->year);

                    return Excel::download(new SupplierMonthMatrixExport($year), "supplier-matrix-{$year}.xlsx");
                }),

            Action::make('exportBudgetVsActual')
                ->label('Export budget vs. actual')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $year = (int) ($this->filters['year'] ?? now()->year);

                    return Excel::download(new BudgetVsActualExport($year), "budget-vs-actual-{$year}.xlsx");
                }),
        ];
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
}
