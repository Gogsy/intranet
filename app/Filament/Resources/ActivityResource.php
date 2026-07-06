<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;
use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\ActivityResource\Pages;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string | \UnitEnum | null $navigationGroup = 'Security';
    protected static ?string $navigationLabel = 'Activity Log';
    protected static ?int $navigationSort = 10;

    /** Security/monitoring data — super admins only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer', 'subject']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Area')->badge()->sortable(),
                TextColumn::make('event')->label('Event')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created', 'login' => 'success',
                        'updated' => 'warning',
                        'deleted', 'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description')->label('Description')->limit(40),
                TextColumn::make('causer.name')->label('Who')->default('— system —')->searchable(),
                TextColumn::make('subject_type')->label('On')
                    ->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state) . ' #' . $record->subject_id : '—'),
                TextColumn::make('properties.ip')->label('IP')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Area')
                    ->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->toArray()),
                SelectFilter::make('event')->label('Event')
                    ->options(['login' => 'Login', 'logout' => 'Logout', 'failed' => 'Failed login', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(), // prune old entries
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')->columns(2)->schema([
                TextEntry::make('created_at')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('log_name')->label('Area')->badge(),
                TextEntry::make('event')->badge(),
                TextEntry::make('description'),
                TextEntry::make('causer.name')->label('Who')->default('— system —'),
                TextEntry::make('subject_type')->label('On')
                    ->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state) . ' #' . $record->subject_id : '—'),
            ]),
            Section::make('Changes')->columns(2)->schema([
                TextEntry::make('changes_old')->label('Before')
                    ->html()->placeholder('—')
                    ->state(fn ($record) => self::pretty(data_get($record->properties, 'old'))),
                TextEntry::make('changes_after')->label('After')
                    ->html()->placeholder('—')
                    ->state(fn ($record) => self::pretty(data_get($record->properties, 'attributes'))),
            ])->visible(fn ($record) => filled(data_get($record->properties, 'attributes')) || filled(data_get($record->properties, 'old'))),
            Section::make('Context')->schema([
                TextEntry::make('all_properties')->label('All properties')
                    ->html()->columnSpanFull()
                    ->state(fn ($record) => self::pretty($record->properties)),
            ]),
        ]);
    }

    /** Safely render an array/collection of properties as a formatted block. */
    protected static function pretty($state): ?HtmlString
    {
        if ($state instanceof Collection) {
            $state = $state->toArray();
        }
        if (blank($state)) {
            return null;
        }
        $text = is_array($state)
            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $state;

        return new HtmlString(
            '<pre style="white-space:pre-wrap;word-break:break-word;margin:0;font-size:12px">' . e($text) . '</pre>'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
