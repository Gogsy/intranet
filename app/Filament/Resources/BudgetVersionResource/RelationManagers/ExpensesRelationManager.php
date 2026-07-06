<?php

namespace App\Filament\Resources\BudgetVersionResource\RelationManagers;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Models\BudgetVersion;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Services\BudgetRules;
use App\Support\BudgetPlannerOptions;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenseItems';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $title = 'Expenses';

    /** Off by default — the selection checkbox column costs width, so bulk mode is opt-in via a header toggle. */
    public bool $bulkSelectionEnabled = false;

    /** The whole Expenses tab exists only for holders of view_budget_expenses. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_budget_expenses') ?? false;
    }

    /** May mutate expense rows (permission; the lock state is checked separately). */
    protected function userCanEditExpenses(): bool
    {
        return auth()->user()?->can('edit_budget_expenses') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('account_code')->label('Account')->maxLength(255),
            TextInput::make('vendor')->label('Vendor')->maxLength(255),
            Textarea::make('description')->label('Description')->rows(2)->columnSpanFull(),
            Textarea::make('comment')->label('Comment')->rows(2)->columnSpanFull(),
            Select::make('expense_type')->label('Type')
                ->options(BudgetPlannerOptions::EXPENSE_TYPES)->default('MONTHLY')->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var BudgetVersion $version */
        $version = $this->getOwnerRecord();

        // Excel-like compact grid: one identity column (name + vendor · account
        // as its second line), the type that drives month auto-fill, then the
        // 12 tight month inputs and the total. Name/vendor/account/comments
        // are edited via the row's Edit modal — keeping them out of inline
        // inputs is what makes the year fit without a horizontal scrollbar.
        $columns = [
            TextColumn::make('name')->label('Expense')
                ->limit(30)
                ->description(fn (ExpenseItem $record) => collect([$record->vendor, $record->account_code])->filter()->implode(' · ') ?: null, position: 'above')
                ->tooltip(fn (ExpenseItem $record) => collect([$record->name, $record->vendor, $record->description, $record->comment])->filter()->implode(' — '))
                // One search box for every text field on the expense.
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('account_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                )),

            SelectColumn::make('expense_type')->label('Type')
                ->options(BudgetPlannerOptions::EXPENSE_TYPES)
                // No empty "Select an option" row — the column is NOT NULL
                // in the DB, so picking it would crash the update.
                ->selectablePlaceholder(false)
                ->extraAttributes(['class' => 'bp-type-select'])
                ->grow(false)
                ->disabled(fn () => ! $version->canEditBudgetValues() || ! $this->userCanEditExpenses()),
        ];

        $canMark = $this->userCanEditExpenses();

        foreach (range(1, 12) as $month) {
            $columns[] = TextInputColumn::make("month_{$month}")
                ->label((string) $month)
                ->type('number')
                ->alignRight()
                ->tooltip(Carbon::create()->month($month)->translatedFormat('F')
                    . ($canMark ? ' — right-click to mark with a colour (payment started)' : ''))
                // data-* attributes feed the right-click colour menu in
                // resources/views/filament/budget/planner-tools.blade.php.
                ->extraAttributes(fn (ExpenseItem $record) => array_filter([
                    'class' => 'bp-month-input',
                    'data-expense-id' => $record->id,
                    'data-month' => $month,
                    'data-mark' => $record->monthValues->firstWhere('month', $month)?->mark_color,
                ]))
                ->grow(false)
                ->state(fn (ExpenseItem $record) => (float) ($record->monthValues->firstWhere('month', $month)?->amount ?? 0))
                ->updateStateUsing(fn (ExpenseItem $record, $state) => $this->applyMonthEntry($record, $month, (float) $state))
                // The editable window intentionally does NOT lock expense
                // cells — every month must stay correctable while unlocked.
                ->disabled(fn () => ! $version->canEditBudgetValues() || ! $this->userCanEditExpenses());
        }

        $columns[] = TextColumn::make('total')->label('Total')
            ->money('EUR')->weight('bold')->alignRight()->grow(false);

        // Comment indicator (mirrors the investments "Note" column): green
        // when the expense has a comment — hover to read it — gray when it
        // has none. Clicking it opens the comment modal (see the row action).
        $columns[] = IconColumn::make('has_comment')
            ->label('')
            ->alignCenter()
            ->grow(false)
            ->state(fn (ExpenseItem $record) => filled($record->comment))
            ->icon(fn (bool $state) => $state ? 'heroicon-s-chat-bubble-bottom-center-text' : 'heroicon-o-chat-bubble-bottom-center-text')
            ->color(fn (bool $state) => $state ? 'success' : 'gray')
            ->tooltip(fn (ExpenseItem $record) => $record->comment ?: 'No comment')
            // Click opens the comment: editable when the row is editable,
            // read-only otherwise.
            ->action(
                Action::make('comment')
                    ->modalHeading(fn (ExpenseItem $record) => "Comment — {$record->name}")
                    ->modalWidth('md')
                    ->schema(fn () => [
                        Textarea::make('comment')->label('Comment')->rows(4)
                            ->disabled(fn () => ! $version->canEditBudgetValues() || ! $this->userCanEditExpenses()),
                    ])
                    ->fillForm(fn (ExpenseItem $record) => ['comment' => $record->comment])
                    ->modalSubmitAction(fn ($action) => ($version->canEditBudgetValues() && $this->userCanEditExpenses())
                        ? $action->label('Save')
                        : false)
                    ->action(function (ExpenseItem $record, array $data) use ($version) {
                        if ($version->canEditBudgetValues() && $this->userCanEditExpenses()) {
                            $record->update(['comment' => $data['comment']]);
                        }
                    }),
            );

        return $table
            ->recordTitleAttribute('name')
            ->recordClasses(fn () => 'bp-compact')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->searchDebounce('200ms')
            ->modifyQueryUsing(fn ($query) => $query->with('monthValues'))
            ->columns($columns)
            ->filters([
                SelectFilter::make('expense_type')->label('Type')->options(BudgetPlannerOptions::EXPENSE_TYPES),
                SelectFilter::make('vendor')->label('Vendor')
                    ->options(fn () => ExpenseItem::where('budget_version_id', $version->id)->pluck('vendor', 'vendor')->filter()),
            ])
            ->headerActions([
                Action::make('calculator')
                    ->label('Calculator')
                    ->icon('heroicon-o-calculator')
                    ->color('gray')
                    ->tooltip('Toggle the on-screen calculator')
                    // Handled entirely client-side: the floating calculator in
                    // planner-tools.blade.php listens for this window event.
                    ->action(fn () => $this->dispatch('toggle-bp-calculator')),

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
                    ->label('Add expense')
                    ->visible(fn () => $version->canEditBudgetValues() && $this->userCanEditExpenses())
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(255),
                        TextInput::make('account_code')->label('Account')->maxLength(255),
                        TextInput::make('vendor')->label('Vendor')->maxLength(255),
                        Textarea::make('description')->label('Description')->rows(2)->columnSpanFull(),
                        Textarea::make('comment')->label('Comment')->rows(2)->columnSpanFull(),
                        Select::make('expense_type')->label('Type')
                            ->options(BudgetPlannerOptions::EXPENSE_TYPES)->default('MONTHLY')->required()->live(),
                        TextInput::make('generation_amount')->label('Amount')->numeric()->default(0)
                            ->visible(fn (Get $get) => $get('expense_type') !== 'VOLUME'),
                        Select::make('generation_month')->label('Month (for one-time)')
                            ->options(array_combine(range(1, 12), range(1, 12)))->default(1)
                            ->visible(fn (Get $get) => $get('expense_type') === 'ONE_TIME'),
                    ])
                    ->using(function (array $data) use ($version) {
                        return DB::transaction(function () use ($data, $version) {
                            $expense = $version->expenseItems()->create([
                                'name' => $data['name'],
                                'account_code' => $data['account_code'] ?? null,
                                'vendor' => $data['vendor'] ?? null,
                                'description' => $data['description'] ?? null,
                                'comment' => $data['comment'] ?? null,
                                'expense_type' => $data['expense_type'],
                            ]);

                            $months = BudgetRules::generateExpenseMonths(
                                $data['expense_type'],
                                (float) ($data['generation_amount'] ?? 0),
                                (int) ($data['generation_month'] ?? 1),
                            );

                            foreach ($months as $month => $amount) {
                                $expense->monthValues()->create(['month' => $month, 'amount' => $amount]);
                            }

                            return $expense;
                        });
                    }),
            ])
            ->recordActions([
                Action::make('generate')
                    ->label('Generate')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip('Generate months — fills all 12 months from a type and amount (e.g. spread an annual cost)')
                    ->visible(fn () => $version->canEditBudgetValues() && $this->userCanEditExpenses())
                    ->schema([
                        Select::make('expense_type')->label('Type')
                            ->options(BudgetPlannerOptions::EXPENSE_TYPES)->required()->live(),
                        TextInput::make('amount')->label('Amount')->numeric()->required()->default(0),
                        Select::make('month')->label('Month (for one-time)')
                            ->options(array_combine(range(1, 12), range(1, 12)))->default(1)
                            ->visible(fn (Get $get) => $get('expense_type') === 'ONE_TIME'),
                    ])
                    ->fillForm(fn (ExpenseItem $record) => ['expense_type' => $record->expense_type])
                    ->action(function (ExpenseItem $record, array $data) {
                        $record->update(['expense_type' => $data['expense_type']]);

                        $months = BudgetRules::generateExpenseMonths(
                            $data['expense_type'],
                            (float) $data['amount'],
                            (int) ($data['month'] ?? 1),
                        );

                        foreach ($months as $month => $amount) {
                            $record->monthValues()->updateOrCreate(['month' => $month], ['amount' => $amount]);
                        }
                    }),
                EditAction::make()
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Edit name, vendor, account, description and comment')
                    ->visible(fn () => $version->canEditBudgetValues() && $this->userCanEditExpenses()),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete this expense')
                    ->visible(fn () => $version->canEditBudgetValues() && $this->userCanEditExpenses()),
            ])
            ->toolbarActions($this->bulkSelectionEnabled && $this->userCanEditExpenses()
                ? [DeleteBulkAction::make()]
                : []);
    }

    /**
     * Typing an amount into a month cell auto-fills the rest of the row
     * according to the expense type:
     *
     * - ANNUAL_AVR: the typed value is the ANNUAL amount — it is spread
     *   evenly over all 12 months (e.g. 120 in April → 10 in every month),
     *   showing what it costs per month while summing exactly.
     * - ONE_TIME:   the typed value lands in that month, all others reset to 0.
     * - MONTHLY:    the typed value fills this month THROUGH December, earlier
     *   months keep their values — so a mid-year price change (Jan-Mar at the
     *   old price, Apr+ at the new one) takes two keystrokes, not twelve.
     * - VOLUME:     manual mode — only the edited cell changes.
     *
     * @return float the value the edited cell should display afterwards
     */
    protected function applyMonthEntry(ExpenseItem $record, int $month, float $amount): float
    {
        $amount = BudgetRules::roundMoney($amount);

        $values = match ($record->expense_type) {
            'ANNUAL_AVR' => BudgetRules::generateExpenseMonths('ANNUAL_AVR', $amount),
            'ONE_TIME' => BudgetRules::generateExpenseMonths('ONE_TIME', $amount, $month),
            'MONTHLY' => array_fill_keys(range($month, 12), $amount),
            default => [$month => $amount],
        };

        foreach ($values as $m => $value) {
            $record->monthValues()->updateOrCreate(['month' => $m], ['amount' => $value]);
        }

        return $values[$month];
    }

    /**
     * Right-click on a month cell opens a colour menu (planner-tools.blade.php)
     * which calls this to set/clear the cell's "payment started" mark.
     *
     * The mark is tracking metadata, not a budget value, so it deliberately
     * bypasses the version lock guard — marking a payment on last year's
     * locked budget is the whole point of the feature.
     */
    public function setMonthMark(int $expenseItemId, int $month, ?string $color): void
    {
        if (! $this->userCanEditExpenses() || $month < 1 || $month > 12) {
            return;
        }

        if ($color !== null && ! in_array($color, BudgetPlannerOptions::MARK_COLORS, true)) {
            return;
        }

        /** @var ExpenseItem $expense scoped to this version so a crafted id can't touch other budgets */
        $expense = $this->getOwnerRecord()->expenseItems()->findOrFail($expenseItemId);

        ExpenseMonthValue::withoutLockGuard(function () use ($expense, $month, $color) {
            $expense->monthValues()
                ->firstOrCreate(['month' => $month], ['amount' => 0])
                ->update(['mark_color' => $color]);
        });
    }
}
