<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use App\Concerns\CanSkipLockGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseMonthValue extends Model
{
    use LogsModelActivity;
    use CanSkipLockGuard;

    protected $fillable = ['expense_item_id', 'month', 'amount', 'mark_color'];

    protected $casts = [
        'month' => 'integer',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ExpenseMonthValue $value) {
            if (static::$skipLockGuard) {
                return;
            }

            // mark_color is a tracking flag ("payment started this month"),
            // not a budget value — changing it must work on locked versions.
            $dirtyKeys = array_keys(array_diff_key($value->getDirty(), array_flip(['created_at', 'updated_at', 'mark_color'])));

            if (empty($dirtyKeys)) {
                return;
            }

            // Fetch fresh rather than trust a cached relation — see InvestmentItem.
            $expenseItem = ExpenseItem::findOrFail($value->expense_item_id);
            BudgetVersion::findOrFail($expenseItem->budget_version_id)->assertCanEditExpenseValue();
        });
    }

    public function expenseItem(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class);
    }
}
