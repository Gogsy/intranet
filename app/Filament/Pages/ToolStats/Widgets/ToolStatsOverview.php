<?php

namespace App\Filament\Pages\ToolStats\Widgets;

use App\Models\ToolClick;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ToolStatsOverview extends BaseWidget
{
    /** Click stats aren't live data — Filament's default 5s poll was pure overhead. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_tool_stats') ?? false;
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7);
        $monthAgo = now()->subDays(30);

        $topTool = ToolClick::query()
            ->selectRaw('tool_id, count(*) as total')
            ->groupBy('tool_id')
            ->orderByDesc('total')
            ->with('tool')
            ->first();

        return [
            Stat::make('Clicks today', ToolClick::where('created_at', '>=', $today)->count())
                ->color('success'),
            Stat::make('Clicks this week', ToolClick::where('created_at', '>=', $weekAgo)->count())
                ->description('Last 7 days')
                ->color('info'),
            Stat::make('Clicks this month', ToolClick::where('created_at', '>=', $monthAgo)->count())
                ->description('Last 30 days')
                ->color('warning'),
            Stat::make('Total clicks', ToolClick::count())
                ->description('All time')
                ->color('gray'),
            Stat::make('Most used tool', $topTool?->tool?->name ?? '—')
                ->description($topTool ? $topTool->total . ' clicks all time' : 'No clicks yet')
                ->color('primary'),
        ];
    }
}
