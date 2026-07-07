<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ToolStats\Widgets\ToolClicksChart;
use App\Filament\Pages\ToolStats\Widgets\ToolClicksTable;
use App\Filament\Pages\ToolStats\Widgets\ToolStatsOverview;
use Filament\Pages\Page;

class ToolStats extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Tool Stats';
    protected static ?int $navigationSort = 20;

    /** Not held by any role by default — super_admin passes via bypass, assignable from the role edit screen. */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_tool_stats') ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Clicks on Web Tools links — daily/weekly/monthly usage per tool.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ToolStatsOverview::class,
            ToolClicksChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ToolClicksTable::class,
        ];
    }
}
