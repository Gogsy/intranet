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

    /**
     * No self-polling (Filament's default is every 5s): planner-tools.blade.php
     * already $refreshes the page's widgets whenever the data fingerprint moves,
     * so polling here only added redundant requests per open browser tab.
     */
    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // monthlyTotals() runs as two SQL aggregates — no relation loading needed.
        $totals = $this->record->monthlyTotals();

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
