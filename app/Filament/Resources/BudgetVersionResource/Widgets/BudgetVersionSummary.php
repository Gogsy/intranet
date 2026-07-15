<?php

namespace App\Filament\Resources\BudgetVersionResource\Widgets;

use App\Models\BudgetVersion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BudgetVersionSummary extends BaseWidget
{
    public ?BudgetVersion $record = null;

    /**
     * No self-polling (Filament's default is every 5s): planner-tools.blade.php
     * already $refreshes the page's widgets whenever the data fingerprint moves,
     * so polling here only added redundant requests per open browser tab.
     */
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $version = $this->record;
        $summary = $version->investmentSummary();

        // Users without expenses visibility get no expense figures at all —
        // "Total" would leak them too (total = investments + expenses).
        $expenseStats = auth()->user()?->can('view_budget_expenses')
            ? [
                Stat::make('Total', Number::currency($version->total(), 'EUR'))->color('gray'),
                Stat::make('Expenses', Number::currency($version->totalExpenses(), 'EUR'))->color('gray'),
            ]
            : [];

        return [
            ...$expenseStats,
            Stat::make('Investments', Number::currency($version->totalInvestments(), 'EUR'))->color('gray'),
            Stat::make('Approved', $summary['approved'])->color('success'),
            Stat::make('Purchased', $summary['purchased'])->color('gray'),
            Stat::make('Purchased w/o approval', $summary['purchasedWithoutApproval'])
                ->color($summary['purchasedWithoutApproval'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
