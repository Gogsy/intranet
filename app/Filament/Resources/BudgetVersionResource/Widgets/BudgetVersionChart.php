<?php

namespace App\Filament\Resources\BudgetVersionResource\Widgets;

use App\Models\BudgetVersion;
use Filament\Widgets\ChartWidget;

/**
 * Base for the two monthly bar charts on the budget edit page —
 * investments and expenses are plotted as separate side-by-side widgets.
 */
abstract class BudgetVersionChart extends ChartWidget
{
    public ?BudgetVersion $record = null;

    /** Which side of monthlyTotals() this chart plots: 'investments' or 'expenses'. */
    protected static string $dataset = 'investments';

    protected static string $barColor = '#f59e0b';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $version = $this->record->fresh(['investmentItems', 'expenseItems.monthValues']);
        $totals = $version->monthlyTotals();

        return [
            'datasets' => [
                [
                    'label' => $this->getHeading(),
                    'data' => array_map(fn ($month) => $totals[$month][static::$dataset], range(1, 12)),
                    'backgroundColor' => static::$barColor,
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }
}
