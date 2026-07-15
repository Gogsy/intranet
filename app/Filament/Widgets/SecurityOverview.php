<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Spatie\Activitylog\Models\Activity;

class SecurityOverview extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /** A 14-day trend doesn't need Filament's default 5s poll — it re-queried the log constantly. */
    protected ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return 'Security & Monitoring — last 14 days';
    }

    /** view_security holders (super_admin + security_overview). */
    public static function canView(): bool
    {
        return auth()->user()?->can('view_security') ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = 14;
        $start = now()->subDays($days - 1)->startOfDay();

        $byDayAndEvent = Activity::where('created_at', '>=', $start)
            ->get(['created_at', 'event'])
            ->groupBy(fn (Activity $a) => $a->created_at->format('Y-m-d'));

        $labels = [];
        $logins = [];
        $failed = [];
        $changes = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d.m');

            $dayEvents = $byDayAndEvent->get($key, collect());
            $logins[] = $dayEvents->where('event', 'login')->count();
            $failed[] = $dayEvents->where('event', 'failed')->count();
            $changes[] = $dayEvents->whereIn('event', ['created', 'updated', 'deleted'])->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Successful logins',
                    'data' => $logins,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Failed logins',
                    'data' => $failed,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Changes',
                    'data' => $changes,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
