<?php

namespace App\Filament\Resources\BudgetVersionResource\Widgets;

class BudgetVersionExpensesChart extends BudgetVersionChart
{
    protected ?string $heading = 'Monthly overview — expenses';

    protected static string $dataset = 'expenses';

    protected static string $barColor = '#3b82f6';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_budget_expenses') ?? false;
    }
}
