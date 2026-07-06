<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\InvestmentItemsRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\BudgetVersionResource\Pages\ListBudgetVersions;
use App\Filament\Resources\BudgetVersionResource\Pages\CreateBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Clusters\BudgetPlanner;
use App\Filament\Pages\BudgetComparison;
use App\Filament\Resources\BudgetVersionResource\Pages;
use App\Models\BudgetVersion;
use App\Support\BudgetPlannerOptions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class BudgetVersionResource extends Resource
{
    protected static ?string $model = BudgetVersion::class;
    protected static ?string $cluster = BudgetPlanner::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Budgets';
    protected static ?string $modelLabel = 'budget';
    protected static ?string $pluralModelLabel = 'budgets';
    protected static ?int $navigationSort = 10;

    /*
     * Granular budget permissions (see RolesAndPermissionsSeeder):
     * `view_budget` opens the list AND the edit page — the edit page is the
     * budget's interior (its inline form is empty; every mutation there is
     * gated separately: settings/lock/import/delete need `manage_budget`,
     * row edits need `edit_budget_items` / `edit_budget_expenses`).
     * Creating/deleting whole budgets is owner-tier: `manage_budget`.
     */

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_budget') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_budget') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name')
                ->placeholder('e.g. IT Investments FC1 2026')
                ->required()
                ->maxLength(255),

            // Plain year number — the BudgetYear row is found/created behind
            // the scenes (see Create/Edit pages); "years" are not a concept
            // the user manages separately anymore.
            TextInput::make('year')
                ->label('Year')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->required()
                ->formatStateUsing(fn (?BudgetVersion $record) => $record?->budgetYear?->year ?? now()->addYear()->year)
                ->dehydrated(),

            Select::make('type')
                ->label('Type')
                ->options(BudgetPlannerOptions::VERSION_TYPES)
                ->required()
                ->live()
                // Picking a type suggests its usual month range, but you can override below.
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if ($state) {
                        $window = BudgetVersion::editableWindowFor($state);
                        $set('editable_from_month', $window['from']);
                        $set('editable_to_month', $window['to']);
                    }
                }),

            Grid::make(2)->schema([
                Select::make('editable_from_month')
                    ->label('Editable from month')
                    ->helperText('Months before this are read-only (already spent/closed).')
                    ->options(array_combine(range(1, 12), range(1, 12)))
                    ->default(1)
                    ->required(),

                Select::make('editable_to_month')
                    ->label('Editable to month')
                    ->options(array_combine(range(1, 12), range(1, 12)))
                    ->default(12)
                    ->required()
                    ->gte('editable_from_month'),
            ]),

            Select::make('template_version_id')
                ->label('Copy rows from (template)')
                ->helperText('Leave empty to start blank. Any existing budget, from any year, can be the template.')
                ->options(fn () => BudgetVersion::with('budgetYear')->get()
                    ->mapWithKeys(fn (BudgetVersion $v) => [$v->id => "{$v->budgetYear->year} — {$v->name}"]))
                ->searchable()
                ->visible(fn (?BudgetVersion $record) => $record === null),

            Select::make('status')
                ->label('Status')
                ->options(BudgetPlannerOptions::VERSION_STATUSES)
                ->default('DRAFT')
                ->required()
                ->visible(fn (?BudgetVersion $record) => $record !== null)
                ->helperText('Locking/unlocking via the Lock/Unlock header buttons also records the audit trail — prefer those for lock changes.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50, 100, 'all'])
            // Live totals/status: reflect other users' changes without a reload.
            ->poll('5s')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->weight('bold'),
                TextColumn::make('budgetYear.year')->label('Year')->sortable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('editable_from_month')->label('Editable months')
                    ->formatStateUsing(fn ($record) => "{$record->editable_from_month}–{$record->editable_to_month}")
                    ->tooltip('Months you can still edit; earlier months are read-only.'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => BudgetPlannerOptions::VERSION_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'DRAFT' => 'gray',
                        'LOCKED' => 'danger',
                        'TEMPORARILY_UNLOCKED' => 'warning',
                        'ARCHIVED' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(BudgetPlannerOptions::VERSION_TYPES),
                SelectFilter::make('status')->label('Status')->options(BudgetPlannerOptions::VERSION_STATUSES),
                SelectFilter::make('budget_year_id')->label('Year')->relationship('budgetYear', 'year'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkAction::make('compare')
                    ->label('Compare selected')
                    ->icon('heroicon-o-arrows-right-left')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        if ($records->count() !== 2) {
                            Notification::make()
                                ->title('Select exactly 2 budgets to compare.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        [$old, $new] = $records->sortBy('created_at')->values();

                        return redirect(BudgetComparison::getUrl(['old' => $old->id, 'new' => $new->id]));
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InvestmentItemsRelationManager::class,
            ExpensesRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgetVersions::route('/'),
            'create' => CreateBudgetVersion::route('/create'),
            'edit' => EditBudgetVersion::route('/{record}/edit'),
        ];
    }
}
