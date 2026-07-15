<?php

namespace App\Filament\Pages\ToolStats\Widgets;

use App\Models\ToolClick;
use Filament\Widgets\ChartWidget;

class ToolClicksChart extends ChartWidget
{
    protected ?string $heading = 'Daily clicks (last 30 days)';

    /** Click stats aren't live data — Filament's default 5s poll was pure overhead. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_tool_stats') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = 30;
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = ToolClick::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (ToolClick $click) => $click->created_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $labels[] = $date->format('d.m');
            $data[] = $counts->get($date->format('Y-m-d'), 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clicks',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
