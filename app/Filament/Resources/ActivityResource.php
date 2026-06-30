<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?int $navigationSort = 40;

    /** Security/monitoring data — super admins only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d.m.Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('log_name')->label('Area')->badge()->sortable(),
                Tables\Columns\TextColumn::make('event')->label('Event')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created', 'login' => 'success',
                        'updated' => 'warning',
                        'deleted', 'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')->label('Description')->limit(40),
                Tables\Columns\TextColumn::make('causer.name')->label('Who')->default('— system —')->searchable(),
                Tables\Columns\TextColumn::make('subject_type')->label('On')
                    ->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state) . ' #' . $record->subject_id : '—'),
                Tables\Columns\TextColumn::make('properties.ip')->label('IP')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')->label('Area')
                    ->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->toArray()),
                Tables\Filters\SelectFilter::make('event')->label('Event')
                    ->options(['login' => 'Login', 'logout' => 'Logout', 'failed' => 'Failed login', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(), // prune old entries
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Event')->columns(2)->schema([
                Infolists\Components\TextEntry::make('created_at')->dateTime('d.m.Y H:i:s'),
                Infolists\Components\TextEntry::make('log_name')->label('Area')->badge(),
                Infolists\Components\TextEntry::make('event')->badge(),
                Infolists\Components\TextEntry::make('description'),
                Infolists\Components\TextEntry::make('causer.name')->label('Who')->default('— system —'),
                Infolists\Components\TextEntry::make('subject_type')->label('On')
                    ->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state) . ' #' . $record->subject_id : '—'),
            ]),
            Infolists\Components\Section::make('Changes')->columns(2)->schema([
                Infolists\Components\TextEntry::make('changes_old')->label('Before')
                    ->html()->placeholder('—')
                    ->state(fn ($record) => self::pretty(data_get($record->properties, 'old'))),
                Infolists\Components\TextEntry::make('changes_after')->label('After')
                    ->html()->placeholder('—')
                    ->state(fn ($record) => self::pretty(data_get($record->properties, 'attributes'))),
            ])->visible(fn ($record) => filled(data_get($record->properties, 'attributes')) || filled(data_get($record->properties, 'old'))),
            Infolists\Components\Section::make('Context')->schema([
                Infolists\Components\TextEntry::make('all_properties')->label('All properties')
                    ->html()->columnSpanFull()
                    ->state(fn ($record) => self::pretty($record->properties)),
            ]),
        ]);
    }

    /** Safely render an array/collection of properties as a formatted block. */
    protected static function pretty($state): ?\Illuminate\Support\HtmlString
    {
        if ($state instanceof \Illuminate\Support\Collection) {
            $state = $state->toArray();
        }
        if (blank($state)) {
            return null;
        }
        $text = is_array($state)
            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $state;

        return new \Illuminate\Support\HtmlString(
            '<pre style="white-space:pre-wrap;word-break:break-word;margin:0;font-size:12px">' . e($text) . '</pre>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
