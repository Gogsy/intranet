<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use App\Exceptions\BudgetVersionLockedException;
use App\Services\BudgetRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class BudgetVersion extends Model
{
    use LogsModelActivity;

    /** Investment fields that stay editable after lock — realization tracking, not budget values. */
    public const REALIZATION_ONLY_FIELDS = ['decision_status', 'purchased', 'realization_comment'];

    protected $fillable = [
        'budget_year_id', 'type', 'name', 'baseline_version_id',
        'editable_from_month', 'editable_to_month', 'status',
        'locked_at', 'unlocked_at',
    ];

    protected $casts = [
        'editable_from_month' => 'integer',
        'editable_to_month' => 'integer',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function budgetYear(): BelongsTo
    {
        return $this->belongsTo(BudgetYear::class);
    }

    public function baselineVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baseline_version_id');
    }

    public function investmentItems(): HasMany
    {
        return $this->hasMany(InvestmentItem::class);
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function unlockEvents(): HasMany
    {
        return $this->hasMany(UnlockEvent::class);
    }

    /** Editable month window (1-12) for each version type, computed once at creation. */
    public static function editableWindowFor(string $type): array
    {
        return BudgetRules::editableWindowFor($type);
    }

    public function isMonthEditable(int $month): bool
    {
        return $month >= $this->editable_from_month && $month <= $this->editable_to_month;
    }

    public function canEditBudgetValues(): bool
    {
        return in_array($this->status, ['DRAFT', 'TEMPORARILY_UNLOCKED'], true);
    }

    /**
     * Enforces the realization-fields-stay-editable-while-locked rule for
     * investments. Used both by Filament `disabled()` closures (UI) and
     * inside model/import persistence paths, so a bypass via import/tinker/
     * bulk-action can't silently violate a locked version.
     *
     * @param  array<int, string>  $dirtyKeys
     */
    public function assertCanEditInvestmentFields(array $dirtyKeys, ?int $month = null): void
    {
        // System/CLI contexts (no auth user) skip the permission tier — the
        // lock/window rules below still apply. Permission tier: `manage_budget`
        // holders (and super_admin via the Shield bypass) are budget owners.
        $user = auth()->user();
        $isOwner = $user === null || $user->can('manage_budget');

        // Decisions are owner-tier no matter the lock state — the UI disables
        // the field, this guard stops forged/scripted writes too.
        if (! $isOwner && in_array('decision_status', $dirtyKeys, true)) {
            throw new BudgetVersionLockedException('Only a budget owner can change the decision status.');
        }

        if (! $isOwner && ! $user->can('edit_budget_items')) {
            throw new BudgetVersionLockedException('You are not allowed to edit investment rows.');
        }

        $realizationOnly = collect($dirtyKeys)->every(fn ($key) => in_array($key, self::REALIZATION_ONLY_FIELDS, true));

        if ($realizationOnly) {
            // Realization tracking ignores the month window; on a LOCKED
            // budget it stays editable for owners only — everyone else is
            // fully frozen.
            if ($isOwner || $this->canEditBudgetValues()) {
                return;
            }

            throw new BudgetVersionLockedException('Budget version is locked.');
        }

        if (! $this->canEditBudgetValues()) {
            throw new BudgetVersionLockedException('Budget version is locked. Use admin unlock before editing budget values.');
        }

        if ($month !== null && ! $this->isMonthEditable($month)) {
            throw new BudgetVersionLockedException("Month {$month} is outside the editable window for {$this->type}.");
        }
    }

    /**
     * Enforces the lock rule for expenses. Unlike investments, the editable
     * month window does NOT apply here — expense rows span all 12 months and
     * every cell must stay correctable (actuals for past months included)
     * while the version is unlocked.
     */
    public function assertCanEditExpenseValue(): void
    {
        // See assertCanEditInvestmentFields: no auth user = system context.
        $user = auth()->user();

        if ($user !== null && ! $user->can('edit_budget_expenses') && ! $user->can('manage_budget')) {
            throw new BudgetVersionLockedException('You are not allowed to edit expense rows.');
        }

        if (! $this->canEditBudgetValues()) {
            throw new BudgetVersionLockedException('Budget version is locked. Use admin unlock before editing budget values.');
        }
    }

    /*
     * The totals below are SQL aggregates, not collection sums — the summary
     * and chart widgets call them on every refresh, and loading every
     * investment/expense row into PHP just to add them up made each widget
     * refresh cost the whole dataset. ROUND(..., 2) inside SUM mirrors the
     * old per-item BudgetRules::investmentTotal() rounding exactly.
     */

    public function totalInvestments(): float
    {
        return BudgetRules::roundMoney(
            (float) $this->investmentItems()->sum(DB::raw('ROUND(quantity * unit_net_price, 2)'))
        );
    }

    public function totalExpenses(): float
    {
        return BudgetRules::roundMoney(
            (float) ExpenseMonthValue::whereIn('expense_item_id', $this->expenseItems()->select('id'))->sum('amount')
        );
    }

    public function total(): float
    {
        return BudgetRules::roundMoney($this->totalInvestments() + $this->totalExpenses());
    }

    /** @return array{approved: int, purchased: int, purchasedWithoutApproval: int} */
    public function investmentSummary(): array
    {
        $row = $this->investmentItems()
            ->selectRaw("COUNT(CASE WHEN decision_status = 'Approved' THEN 1 END) AS approved")
            ->selectRaw('COUNT(CASE WHEN purchased THEN 1 END) AS purchased')
            ->selectRaw("COUNT(CASE WHEN purchased AND decision_status <> 'Approved' THEN 1 END) AS purchased_without_approval")
            ->toBase()
            ->first();

        return [
            'approved' => (int) ($row->approved ?? 0),
            'purchased' => (int) ($row->purchased ?? 0),
            'purchasedWithoutApproval' => (int) ($row->purchased_without_approval ?? 0),
        ];
    }

    /**
     * Monthly investment/expense totals for the chart + stats widgets.
     *
     * @return array<int, array{investments: float, expenses: float}> keyed by month (1-12)
     */
    public function monthlyTotals(): array
    {
        $investments = $this->investmentItems()
            ->selectRaw('month, SUM(ROUND(quantity * unit_net_price, 2)) AS total')
            ->groupBy('month')
            ->toBase()
            ->pluck('total', 'month');

        $expenses = ExpenseMonthValue::whereIn('expense_item_id', $this->expenseItems()->select('id'))
            ->selectRaw('month, SUM(amount) AS total')
            ->groupBy('month')
            ->toBase()
            ->pluck('total', 'month');

        $totals = [];
        foreach (range(1, 12) as $month) {
            $totals[$month] = [
                'investments' => BudgetRules::roundMoney((float) ($investments[$month] ?? 0)),
                'expenses' => BudgetRules::roundMoney((float) ($expenses[$month] ?? 0)),
            ];
        }

        return $totals;
    }
}
