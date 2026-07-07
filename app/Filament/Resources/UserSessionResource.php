<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserSessionResource\Pages\ListUserSessions;
use App\Models\UserSession;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Live view of the `sessions` table: who is signed in, from where, on what
 * device, and when they were last active. "Revoke" deletes the session row,
 * forcing that browser to log in again (equivalent of the user-sessions
 * plugins that don't support Filament v5 yet).
 */
class UserSessionResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static string | \UnitEnum | null $navigationGroup = 'Security';
    protected static ?string $navigationLabel = 'Active Sessions';
    protected static ?string $modelLabel = 'session';
    protected static ?string $pluralModelLabel = 'Active Sessions';
    protected static ?int $navigationSort = 3;

    /** Security/monitoring data — view_security holders (super_admin + security_overview). */
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_security') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity', 'desc')
            ->poll('10s')
            ->columns([
                IconColumn::make('online')
                    ->label('')
                    ->state(fn (UserSession $record) => $record->isOnline())
                    ->icon('heroicon-s-signal')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray')
                    ->tooltip(fn (UserSession $record) => $record->isOnline()
                        ? 'Online (active in the last ' . UserSession::ONLINE_MINUTES . ' min)'
                        : 'Idle')
                    ->grow(false),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('— guest —')
                    ->description(fn (UserSession $record) => $record->user?->email)
                    ->searchable(),

                TextColumn::make('ip_address')
                    ->label('IP address')
                    ->searchable(),

                TextColumn::make('device')
                    ->label('Device')
                    ->state(fn (UserSession $record) => $record->deviceLabel())
                    ->tooltip(fn (UserSession $record) => $record->user_agent),

                TextColumn::make('last_activity')
                    ->label('Last activity')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::createFromTimestamp($state)
                        ->timezone(config('app.timezone'))
                        ->format('d.m.Y H:i:s'))
                    ->description(fn (UserSession $record) => \Carbon\Carbon::createFromTimestamp($record->last_activity)->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('signed_in')
                    ->label('Signed in')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('user_id'),
                        false: fn (Builder $query) => $query->whereNull('user_id'),
                    ),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->modalHeading('Revoke session')
                    ->modalDescription(fn (UserSession $record) => $record->id === session()->getId()
                        ? 'This is YOUR current session — revoking it logs you out immediately.'
                        : 'The user will be logged out of this browser on their next request.')
                    ->successNotificationTitle('Session revoked'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->label('Revoke selected'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserSessions::route('/'),
        ];
    }
}
