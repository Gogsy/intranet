<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use App\Concerns\CanSkipLockGuard;
use App\Concerns\HasOriginLineage;
use App\Services\BudgetRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseItem extends Model
{
    use LogsModelActivity;
    use HasOriginLineage;
    use CanSkipLockGuard;

    protected $fillable = [
        'origin_id', 'budget_version_id', 'name', 'account_code', 'vendor',
        'supplier_id', 'description', 'comment', 'expense_type',
    ];

    protected static function booted(): void
    {
        static::saving(function (ExpenseItem $item) {
            if (static::$skipLockGuard) {
                return;
            }

            $dirtyKeys = array_keys(array_diff_key($item->getDirty(), array_flip(['created_at', 'updated_at'])));

            if (empty($dirtyKeys)) {
                return;
            }

            // Fetch fresh rather than trust a cached relation — see InvestmentItem.
            BudgetVersion::findOrFail($item->budget_version_id)->assertCanEditExpenseValue();
        });
    }

    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class);
    }

    public function monthValues(): HasMany
    {
        return $this->hasMany(ExpenseMonthValue::class)->orderBy('month');
    }

    /** Invoice Tracker link, resolved from the free-text vendor by the sync. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getTotalAttribute(): float
    {
        return BudgetRules::expenseTotal(
            $this->monthValues->pluck('amount', 'month')->map(fn ($amount) => (float) $amount)->all()
        );
    }
}
