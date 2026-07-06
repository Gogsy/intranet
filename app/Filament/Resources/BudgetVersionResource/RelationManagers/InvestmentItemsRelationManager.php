<?php

namespace App\Filament\Resources\BudgetVersionResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use App\Models\InvestmentType;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Models\BudgetVersion;
use App\Models\InvestmentItem;
use App\Support\BudgetPlannerOptions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class InvestmentItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'investmentItems';
    protected static ?string $recordTitleAttribute = 'description';
    protected static ?string $title = 'Investments';

    /** Off by default — the selection checkbox column costs width, so bulk mode is opt-in via a header toggle. */
    public bool $bulkSelectionEnabled = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('month')
                ->label('Month')
                ->options(array_combine(range(1, 12), range(1, 12)))
                ->required(),

            Select::make('investment_type_id')
                ->label('Investment type')
                ->relationship('investmentType', 'name')
                ->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->label('Name')->required()])
                ->required(),

            Select::make('classification')
                ->label('Classification')
                ->options(BudgetPlannerOptions::CLASSIFICATIONS)
                ->required(),

            TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()->required()->default(1),

            TextInput::make('unit_net_price')
                ->label('Unit net price')
                ->numeric()->required()->default(0),

            TextInput::make('description')
                ->label('Description')
                ->required()->maxLength(255)->columnSpanFull(),

            Textarea::make('proposal_comment')
                ->label('Comment / proposal')
                ->rows(2)->columnSpanFull(),

            TextInput::make('link_or_description')
                ->label('Link or details')
                ->maxLength(255)->columnSpanFull(),

            Select::make('decision_status')
                ->label('Decision status')
                ->options(BudgetPlannerOptions::INVESTMENT_DECISION_STATUSES)
                ->default('Proposed')->required()
                // Decisions are owner-tier — read-only in the modal too, and
                // never submitted, so a forged POST cannot change it either.
                ->disabled(fn () => ! $this->userCanManageBudget())
                ->dehydrated(fn () => $this->userCanManageBudget()),

            Toggle::make('purchased')
                ->label('Purchased'),

            Textarea::make('realization_comment')
                ->label('Note')
                ->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var BudgetVersion $version */
        $version = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('month')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->searchDebounce('200ms')
            // Whole-row coloring by decision status; "purchased without
            // approval" is the strongest (red) flag and wins over the rest.
            // bp-compact applies the same tight/small-font styling as expenses.
            ->recordClasses(fn (InvestmentItem $record) => ['bp-compact', match (true) {
                $record->purchased && $record->decision_status !== 'Approved' => 'bp-row-warning',
                $record->decision_status === 'Approved' => 'bp-row-approved',
                $record->decision_status === 'Rejected' => 'bp-row-rejected',
                $record->decision_status === 'Deferred' => 'bp-row-deferred',
                default => null,
            }])
            ->columns([
                // Column order mirrors the department's requested layout:
                // Month | Last edited | Type | Description | Qty | Price |
                // Total | Class | Link | Status | Purchased | Note | actions.
                SelectColumn::make('month')
                    ->label('Month')
                    ->options(array_combine(range(1, 12), range(1, 12)))
                    // No empty "Select an option" row — the column is NOT NULL
                    // in the DB, so picking it would crash the update.
                    ->selectablePlaceholder(false)
                    ->extraAttributes(['class' => 'bp-narrow-select'])
                    ->grow(false)
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                // Stamped with the logged-in user by touchEnteredBy() on every
                // inline edit / create / toggle in this row.
                TextColumn::make('enteredBy.name')
                    ->label('Last edited')->default('—')
                    ->limit(14)
                    ->tooltip(fn (InvestmentItem $record) => $record->enteredBy?->name)
                    ->grow(false),

                SelectColumn::make('investment_type_id')
                    ->label('Type')
                    ->options(fn () => InvestmentType::orderBy('sort_order')->pluck('name', 'id'))
                    ->selectablePlaceholder(false)
                    ->extraAttributes(['class' => 'bp-type-select'])
                    ->grow(false)
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                TextInputColumn::make('description')
                    ->label('Description')
                    // One search box covering everything a user might type:
                    // description, comments, link, type name, who edited it.
                    ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('proposal_comment', 'like', "%{$search}%")
                        ->orWhere('realization_comment', 'like', "%{$search}%")
                        ->orWhere('link_or_description', 'like', "%{$search}%")
                        ->orWhereHas('investmentType', fn ($t) => $t->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('enteredBy', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ))
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                TextInputColumn::make('quantity')
                    ->label('Qty')->type('number')
                    ->alignRight()
                    ->extraAttributes(['class' => 'bp-num-input'])
                    ->grow(false)
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                TextInputColumn::make('unit_net_price')
                    ->label('Price')->type('number')
                    ->tooltip('Unit net price')
                    ->alignRight()
                    ->extraAttributes(['class' => 'bp-num-input'])
                    ->grow(false)
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                TextColumn::make('total')->label('Total')
                    ->money('EUR')->weight('bold')->alignRight()->grow(false),

                SelectColumn::make('classification')
                    ->label('Class')
                    ->tooltip('Classification (Asset / Consumable / Rent)')
                    ->options(BudgetPlannerOptions::CLASSIFICATIONS)
                    ->selectablePlaceholder(false)
                    ->extraAttributes(['class' => 'bp-type-select'])
                    ->grow(false)
                    ->disabled(fn (InvestmentItem $record) => ! $this->canEditBudgetFields($record))
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                SelectColumn::make('decision_status')
                    ->label('Status')
                    ->options(BudgetPlannerOptions::INVESTMENT_DECISION_STATUSES)
                    ->selectablePlaceholder(false)
                    ->extraAttributes(['class' => 'bp-type-select'])
                    ->grow(false)
                    // Decisions are owner-tier — item editors see it read-only.
                    ->disabled(fn () => ! $this->userCanManageBudget())
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                // Blue icon = the row has a link (click opens it in a new
                // tab); gray icon = no link. Free-text "link or description"
                // values that aren't URLs show as a tooltip instead of navigating.
                IconColumn::make('link_or_description')
                    ->label('Link')
                    ->alignCenter()
                    ->grow(false)
                    // IconColumn renders nothing at all for a null state, so the
                    // state is a boolean (has link?) — that way the icon always
                    // shows and only its color/click behaviour differs.
                    ->state(fn (InvestmentItem $record) => filled($record->link_or_description))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color(fn (bool $state) => $state ? 'info' : 'gray')
                    ->url(fn (InvestmentItem $record) => str_starts_with((string) $record->link_or_description, 'http') ? $record->link_or_description : null)
                    ->openUrlInNewTab()
                    ->tooltip(fn (InvestmentItem $record) => filled($record->link_or_description) ? $record->link_or_description : 'No link'),

                CheckboxColumn::make('purchased')
                    ->label('Purchased')
                    ->alignCenter()
                    ->grow(false)
                    // Realization field: owners may toggle even on a locked
                    // budget; item editors only while it is unlocked.
                    ->disabled(fn () => ! $this->userCanMutateRows())
                    ->afterStateUpdated(fn (InvestmentItem $record) => $this->touchEnteredBy($record)),

                // Comment/note indicator: green when the row has a proposal or
                // realization note (hover to read them), orange when it has
                // none. Notes are edited via ✏️.
                IconColumn::make('note')
                    ->label('Note')
                    ->alignCenter()
                    ->grow(false)
                    ->state(fn (InvestmentItem $record) => filled($record->proposal_comment) || filled($record->realization_comment))
                    ->icon(fn (bool $state) => $state ? 'heroicon-s-chat-bubble-bottom-center-text' : 'heroicon-o-chat-bubble-bottom-center-text')
                    ->color(fn (bool $state) => $state ? 'success' : 'warning')
                    ->tooltip(fn (InvestmentItem $record) => collect([
                        $record->proposal_comment,
                        $record->realization_comment,
                    ])->filter()->implode("\n") ?: 'No note'),
            ])
            ->filters([
                SelectFilter::make('month')->label('Month')->options(array_combine(range(1, 12), range(1, 12))),
                SelectFilter::make('investment_type_id')->label('Type')->relationship('investmentType', 'name'),
                SelectFilter::make('classification')->label('Classification')->options(BudgetPlannerOptions::CLASSIFICATIONS),
                SelectFilter::make('decision_status')->label('Decision')->options(BudgetPlannerOptions::INVESTMENT_DECISION_STATUSES),
                TernaryFilter::make('purchased')->label('Purchased'),
            ])
            ->headerActions([
                Action::make('toggleBulkSelection')
                    ->label('Toggle select')
                    ->icon(fn () => $this->bulkSelectionEnabled ? 'heroicon-s-check-circle' : 'heroicon-o-check-circle')
                    ->color(fn () => $this->bulkSelectionEnabled ? 'primary' : 'gray')
                    ->tooltip('Toggle row selection (checkboxes for bulk delete)')
                    // Marker class the one-row header CSS hooks onto (AdminPanelProvider).
                    ->extraAttributes(['class' => 'bp-one-row-header'])
                    ->action(function () {
                        $this->bulkSelectionEnabled = ! $this->bulkSelectionEnabled;
                        // The table was already built (and cached) before this
                        // action ran — rebuild it so the checkbox column
                        // appears in this render, not the next one.
                        $this->bootedInteractsWithTable();
                    }),

                CreateAction::make()
                    ->label('Add investment')
                    ->visible(fn () => $this->userCanEditItems() && $version->canEditBudgetValues())
                    ->mutateDataUsing(function (array $data) {
                        $data['entered_by_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Edit all fields, including comments and the link')
                    ->visible(fn () => $this->userCanMutateRows())
                    ->mutateDataUsing(function (array $data) {
                        $data['entered_by_id'] = auth()->id();
                        return $data;
                    }),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete this investment')
                    ->visible(fn () => $this->userCanMutateRows()),
            ])
            ->toolbarActions($this->bulkSelectionEnabled && $this->userCanMutateRows()
                ? [DeleteBulkAction::make()]
                : []);
    }

    /** Whether budget-defining fields (not just realization tracking) can be edited for this row. */
    protected function canEditBudgetFields(InvestmentItem $record): bool
    {
        /** @var BudgetVersion $version */
        $version = $this->getOwnerRecord();

        return $this->userCanEditItems()
            && $version->canEditBudgetValues()
            && $version->isMonthEditable($record->month);
    }

    /** May add/edit/delete investment rows at all (permission; lock state is checked separately). */
    protected function userCanEditItems(): bool
    {
        return auth()->user()?->can('edit_budget_items') ?? false;
    }

    /** Owner tier: decision, and realization edits even while locked. */
    protected function userCanManageBudget(): bool
    {
        return auth()->user()?->can('manage_budget') ?? false;
    }

    /**
     * Row-level actions (edit modal, delete): owners always may; item editors
     * only while the budget is unlocked — on a locked budget EVERYTHING below
     * is frozen for them.
     */
    protected function userCanMutateRows(): bool
    {
        /** @var BudgetVersion $version */
        $version = $this->getOwnerRecord();

        return $this->userCanManageBudget()
            || ($this->userCanEditItems() && $version->canEditBudgetValues());
    }

    /** Stamps the current user as having last touched this row (like the original app's "Last Edited"). */
    protected function touchEnteredBy(InvestmentItem $record): void
    {
        $record->entered_by_id = auth()->id();
        $record->saveQuietly();
    }
}
