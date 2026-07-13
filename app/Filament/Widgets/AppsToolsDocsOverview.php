<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\DocNode;
use App\Models\Tool;
use App\Models\ToolClick;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppsToolsDocsOverview extends BaseWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Applications, Tools & Documentation';

    /** Visible to anyone holding at least one of the three module permissions (super_admin bypass + admin hold all three). */
    public static function canView(): bool
    {
        return auth()->user()?->canAny(['view_apps', 'view_tools', 'view_docs']) ?? false;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('App Downloads', Application::count())
                ->description('Objavljenih aplikacija')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info'),
            Stat::make('Web alata u portalu', Tool::count())
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('info'),
            Stat::make('Klikova na alate (7 dana)', ToolClick::where('created_at', '>=', now()->subDays(7))->count())
                ->icon('heroicon-o-cursor-arrow-rays')
                ->color('primary'),
            Stat::make('Dokumenata u portalu', DocNode::count())
                ->description(DocNode::active()->count() . ' aktivnih')
                ->icon('heroicon-o-book-open')
                ->color('gray'),
        ];
    }
}
