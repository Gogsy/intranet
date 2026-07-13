<?php

namespace App\Filament\InvoiceTracker\Widgets\Analysis;

use App\Models\Invoice;
use App\Models\Supplier;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopSuppliersTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => 'Suppliers in detail — '.$this->getYear())
            ->query(function (): Builder {
                $year = $this->getYear();

                // For the current year, compare the same period (Jan..current month) of last year.
                $comparableMonths = $year === now()->year ? now()->month : 12;

                return Supplier::query()
                    ->where(fn ($q) => $q
                        ->where('is_active', true)
                        ->orWhereHas('invoices', fn ($q) => $q->where('year', $year)))
                    ->withSum(['invoices as spent_total' => fn ($q) => $q->where('year', $year)], 'amount')
                    ->withSum(['invoices as prev_spent' => fn ($q) => $q->where('year', $year - 1)->where('month', '<=', $comparableMonths)], 'amount')
                    ->withSum(['budgets as budget_total' => fn ($q) => $q->where('year', $year)], 'amount')
                    ->addSelect([
                        'active_months' => Invoice::query()
                            ->selectRaw('COUNT(DISTINCT month)')
                            ->whereColumn('supplier_id', 'suppliers.id')
                            ->where('year', $year),
                    ]);
            })
            ->defaultSort('spent_total', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Supplier')
                    ->sortable(),
                TextColumn::make('spent_total')
                    ->label('Spent')
                    ->state(fn (Supplier $record): float => (float) $record->spent_total)
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('share')
                    ->label('Share')
                    ->state(function (Supplier $record): string {
                        $total = $this->getGrandTotal();

                        return $total > 0
                            ? number_format((float) $record->spent_total / $total * 100, 1).'%'
                            : '—';
                    })
                    ->badge()
                    ->color('gray'),
                TextColumn::make('avg_month')
                    ->label('Avg / month')
                    ->state(fn (Supplier $record): float => (int) $record->active_months > 0
                        ? (float) $record->spent_total / (int) $record->active_months
                        : 0)
                    ->money('EUR'),
                TextColumn::make('budget_total')
                    ->label('Budget (year)')
                    ->state(fn (Supplier $record): float => (float) $record->budget_total)
                    ->money('EUR'),
                TextColumn::make('delta')
                    ->label('Budget Δ')
                    ->state(fn (Supplier $record): float => (float) $record->budget_total - (float) $record->spent_total)
                    ->money('EUR')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('yoy')
                    ->label(fn (): string => 'Vs. '.($this->getYear() - 1).' (same period)')
                    ->state(function (Supplier $record): string {
                        $prev = (float) $record->prev_spent;

                        if ($prev <= 0) {
                            return '—';
                        }

                        $change = ((float) $record->spent_total - $prev) / $prev * 100;

                        return ($change >= 0 ? '+' : '').number_format($change, 1).'%';
                    })
                    ->badge()
                    ->color(function (Supplier $record): string {
                        $prev = (float) $record->prev_spent;

                        if ($prev <= 0) {
                            return 'gray';
                        }

                        return (float) $record->spent_total > $prev ? 'warning' : 'success';
                    }),
            ]);
    }

    protected function getYear(): int
    {
        return (int) ($this->pageFilters['year'] ?? now()->year);
    }

    protected function getGrandTotal(): float
    {
        return once(fn (): float => (float) Invoice::query()->forYear($this->getYear())->sum('amount'));
    }
}
