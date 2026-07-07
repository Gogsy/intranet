<?php

namespace App\Filament\Resources\ActivityResource\Pages;

use App\Filament\Resources\ActivityResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Spatie\Activitylog\Models\Activity;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    /**
     * Manual purge of the system Activity Log so it can't grow unbounded and
     * clog the DB. This complements the daily `activitylog:clean` schedule
     * (config/activitylog.php delete_records_older_than_days), which only runs
     * if the server's cron invokes `schedule:run`. Mass deletion is restricted
     * to super_admin — a security_overview user may read the log, not purge it.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearLog')
                ->label('Clear log')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => auth()->user()?->hasRole('super_admin') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Clear activity log')
                ->modalDescription('Permanently deletes entries from the whole system activity log. This cannot be undone.')
                ->schema([
                    Select::make('scope')
                        ->label('Delete')
                        ->options([
                            '7' => 'Entries older than 7 days',
                            '30' => 'Entries older than 30 days',
                            '90' => 'Entries older than 90 days',
                            '365' => 'Entries older than 1 year',
                            'all' => 'All entries',
                        ])
                        ->default('30')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $query = Activity::query();

                    if ($data['scope'] !== 'all') {
                        $query->where('created_at', '<', now()->subDays((int) $data['scope']));
                    }

                    $count = $query->delete();

                    Notification::make()
                        ->title("Deleted {$count} log " . ($count === 1 ? 'entry' : 'entries') . '.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
