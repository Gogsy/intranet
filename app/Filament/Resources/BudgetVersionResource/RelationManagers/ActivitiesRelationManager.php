<?php

namespace App\Filament\Resources\BudgetVersionResource\RelationManagers;

use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Carbon;
use App\Models\BudgetVersion;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\InvestmentItem;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * "Change log" tab: every logged change to this budget AND its rows —
 * investment items, expense items and per-month amounts — including
 * lock/unlock events (the unlock reason is in the activity's properties).
 * Replaces the old unlock-only history tab.
 */
class ActivitiesRelationManager extends RelationManager
{
    // Spatie's LogsActivity provides the `activities` morphMany on the version.
    protected static string $relationship = 'activities';
    protected static ?string $title = 'Change log';

    /** Owner-tier only (currently just super_admin, via the Shield bypass). */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('manage_budget') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var BudgetVersion $version */
        $version = $this->getOwnerRecord();

        return $table
            ->defaultSort('created_at', 'desc')
            // Live change log: pick up other users' edits without a reload.
            ->poll('5s')
            // The relationship only covers activities on the version itself;
            // widen the query to its rows. The relationship's raw wheres are
            // replaced with ONE nested (version OR items) group, so the table
            // filters below AND onto the whole set — top-level orWheres would
            // leak past them (`A OR B AND filter` binds the filter to B only).
            ->modifyQueryUsing(function (Builder $query) use ($version) {
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query
                    ->whereIn('id', $this->versionActivitiesQuery($version)->select('id'))
                    ->with(['causer', 'subject']);
            })
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d.m.Y H:i')->sortable(),

                TextColumn::make('causer.name')->label('Who')->default('—'),

                TextColumn::make('log_name')->label('What')->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'InvestmentItem' => 'Investment',
                        'ExpenseItem' => 'Expense',
                        'ExpenseMonthValue' => 'Expense month',
                        'BudgetVersion' => 'Budget',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'InvestmentItem' => 'warning',
                        'ExpenseItem', 'ExpenseMonthValue' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('event')->label('Event')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'unlocked' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('subject_label')->label('Item')
                    ->state(fn (Activity $record) => $this->subjectLabel($record))
                    ->limit(40)
                    ->tooltip(fn (Activity $record) => $this->subjectLabel($record)),

                TextColumn::make('changes')->label('Changes')
                    ->state(fn (Activity $record) => $this->changesSummary($record))
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Activity $record) => $this->changesSummary($record))
                    // Grouped rows (several folded edits) advertise themselves.
                    ->description(function (Activity $record) {
                        $steps = $record->properties['steps'] ?? null;

                        return is_array($steps) && count($steps) > 1
                            ? count($steps) . ' edits grouped — open for the timeline'
                            : null;
                    }),
            ])
            ->filters([
                SelectFilter::make('causer_id')->label('Who')
                    // Only people who actually appear in this budget's log.
                    ->options(fn () => User::whereIn(
                        'id',
                        $this->versionActivitiesQuery($version)->whereNotNull('causer_id')->distinct()->pluck('causer_id'),
                    )->orderBy('name')->pluck('name', 'id')),

                SelectFilter::make('event')->label('Event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                    'unlocked' => 'Unlocked',
                ]),
                SelectFilter::make('log_name')->label('What')->options([
                    'InvestmentItem' => 'Investment',
                    'ExpenseItem' => 'Expense',
                    'ExpenseMonthValue' => 'Expense month',
                    'BudgetVersion' => 'Budget',
                ]),
            ])
            ->headerActions([
                Action::make('clearLog')
                    ->label('Clear log')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    // Marker class the one-row header CSS hooks onto (AdminPanelProvider).
                    ->extraAttributes(['class' => 'bp-one-row-header'])
                    ->requiresConfirmation()
                    ->modalHeading('Clear change log')
                    ->modalDescription('Deletes change-log entries for this budget and its rows. This cannot be undone.')
                    ->schema([
                        Select::make('scope')
                            ->label('Delete')
                            ->options([
                                '30' => 'Entries older than 30 days',
                                '90' => 'Entries older than 90 days',
                                'all' => 'All entries for this budget',
                            ])
                            ->default('30')
                            ->required(),
                    ])
                    ->action(function (array $data) use ($version) {
                        $query = $this->versionActivitiesQuery($version);

                        if ($data['scope'] !== 'all') {
                            $query->where('created_at', '<', now()->subDays((int) $data['scope']));
                        }

                        $count = $query->delete();

                        Notification::make()
                            ->title("Deleted {$count} log " . ($count === 1 ? 'entry' : 'entries') . '.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->iconButton()
                    ->icon('heroicon-o-eye')
                    ->tooltip('Open the change details / timeline')
                    ->modalHeading(fn (Activity $record) => $this->subjectLabel($record))
                    ->modalDescription(fn (Activity $record) => ($record->causer?->name ?? '—') . ' · ' . $record->created_at?->format('d.m.Y H:i'))
                    ->modalContent(fn (Activity $record) => view('filament.budget.activity-steps', [
                        'steps' => $this->steps($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([])
            ->paginated([25, 50, 100]);
    }

    /**
     * Standalone query over the same set of activities the table shows —
     * the version's own plus those of its investment/expense rows.
     * Used by the "Clear log" action.
     */
    protected function versionActivitiesQuery(BudgetVersion $version): Builder
    {
        $investmentIds = $version->investmentItems()->pluck('id');
        $expenseIds = $version->expenseItems()->pluck('id');
        $monthValueIds = ExpenseMonthValue::whereIn('expense_item_id', $expenseIds)->pluck('id');

        return Activity::query()->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('subject_type', BudgetVersion::class)->where('subject_id', $version->id))
            ->orWhere(fn (Builder $q) => $q->where('subject_type', InvestmentItem::class)->whereIn('subject_id', $investmentIds))
            ->orWhere(fn (Builder $q) => $q->where('subject_type', ExpenseItem::class)->whereIn('subject_id', $expenseIds))
            ->orWhere(fn (Builder $q) => $q->where('subject_type', ExpenseMonthValue::class)->whereIn('subject_id', $monthValueIds)));
    }

    /** Human name of the changed row; survives deletion by falling back to the logged attributes. */
    protected function subjectLabel(Activity $record): string
    {
        $subject = $record->subject;

        return (string) match (true) {
            $subject instanceof InvestmentItem => $subject->description,
            $subject instanceof ExpenseItem => $subject->name,
            $subject instanceof ExpenseMonthValue => ($subject->expenseItem?->name ?? 'Expense') . " — month {$subject->month}",
            $subject instanceof BudgetVersion => $subject->name,
            default => $record->properties['attributes']['description']
                ?? $record->properties['attributes']['name']
                ?? '—',
        };
    }

    /** "field: old → new" per changed field; unlock events show their reason instead. */
    protected function changesSummary(Activity $record): string
    {
        $properties = $record->properties;

        if (filled($properties['reason'] ?? null)) {
            return 'Reason: ' . $properties['reason'];
        }

        return collect($this->fieldChanges(
            collect($properties['attributes'] ?? [])->all(),
            collect($properties['old'] ?? [])->all(),
        ))->implode('; ');
    }

    /**
     * Timeline for the details modal: one entry per individual edit folded
     * into this row by ActivityLogCompactor; plain single-edit rows (and
     * unlock events) become a one-entry timeline.
     *
     * @return array<int, array{at: string, changes: array<int, string>}>
     */
    protected function steps(Activity $record): array
    {
        $properties = $record->properties;

        if (filled($properties['reason'] ?? null)) {
            return [[
                'at' => (string) $record->created_at?->format('d.m.Y H:i:s'),
                'changes' => ['Reason: ' . $properties['reason']],
            ]];
        }

        $steps = $properties['steps'] ?? null;

        if (! is_array($steps)) {
            $steps = [[
                'at' => $record->created_at?->format('Y-m-d H:i:s'),
                'attributes' => collect($properties['attributes'] ?? [])->all(),
                'old' => collect($properties['old'] ?? [])->all(),
            ]];
        }

        return collect($steps)
            ->map(fn (array $step) => [
                'at' => filled($step['at'] ?? null)
                    ? Carbon::parse($step['at'])->format('d.m.Y H:i:s')
                    : '—',
                'changes' => $this->fieldChanges($step['attributes'] ?? [], $step['old'] ?? []),
            ])
            ->all();
    }

    /** @return array<int, string> "field: old → new" lines */
    protected function fieldChanges(array $new, array $old): array
    {
        return collect($new)
            ->map(function ($value, $field) use ($old) {
                $format = fn ($v) => is_scalar($v) || $v === null ? (string) ($v ?? '—') : json_encode($v);

                return array_key_exists($field, $old)
                    ? "{$field}: {$format($old[$field])} → {$format($value)}"
                    : "{$field}: {$format($value)}";
            })
            ->values()
            ->all();
    }
}
