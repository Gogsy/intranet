<?php

namespace App\Filament\Pages\ToolStats\Widgets;

use App\Models\Tool;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ToolClicksTable extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('view_tool_stats') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Clicks per tool')
            ->query(
                Tool::query()
                    ->withCount([
                        'clicks',
                        'clicks as clicks_today' => fn ($query) => $query->where('created_at', '>=', now()->startOfDay()),
                        'clicks as clicks_week' => fn ($query) => $query->where('created_at', '>=', now()->subDays(7)),
                        'clicks as clicks_month' => fn ($query) => $query->where('created_at', '>=', now()->subDays(30)),
                    ])
            )
            ->defaultSort('clicks_count', 'desc')
            ->columns([
                TextColumn::make('name')->label('Tool')->searchable(),
                TextColumn::make('clicks_today')->label('Today')->sortable()->alignCenter(),
                TextColumn::make('clicks_week')->label('7 days')->sortable()->alignCenter(),
                TextColumn::make('clicks_month')->label('30 days')->sortable()->alignCenter(),
                TextColumn::make('clicks_count')->label('All time')->sortable()->alignCenter()->weight('bold'),
            ]);
    }
}
