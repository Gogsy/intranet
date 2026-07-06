<?php

namespace App\Filament\Resources\BudgetVersionResource\Widgets;

class BudgetVersionInvestmentsChart extends BudgetVersionChart
{
    protected ?string $heading = 'Monthly overview — investments';

    protected static string $dataset = 'investments';

    protected static string $barColor = '#f59e0b';

    /** With the expenses chart hidden, take its slot: span the full row. */
    public function getColumnSpan(): int|string|array
    {
        return BudgetVersionExpensesChart::canView() ? parent::getColumnSpan() : 'full';
    }
}
