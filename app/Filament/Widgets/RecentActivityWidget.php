<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    /** Super Admin only — Administrator (admin role) does not see this overview. */
    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Posljednje aktivnosti')
            ->query(
                Activity::query()->with(['causer', 'subject'])->latest('created_at')->limit(8)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Kada')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('event')->label('Događaj')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created', 'login' => 'success',
                        'updated' => 'warning',
                        'deleted', 'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description')->label('Opis')->limit(50),
                TextColumn::make('causer.name')->label('Tko')->default('— sustav —'),
                TextColumn::make('subject_type')->label('Na')
                    ->formatStateUsing(fn (?string $state, $record) => $state ? class_basename($state) . ' #' . $record->subject_id : '—'),
            ]);
    }
}
