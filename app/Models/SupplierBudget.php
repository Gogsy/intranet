<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBudget extends Model
{
    use LogsModelActivity;

    public const string SOURCE_MANUAL = 'manual';

    public const string SOURCE_BUDGET_PLANNER = 'budget_planner';

    protected $fillable = [
        'supplier_id',
        'category_id',
        'year',
        'month',
        'amount',
        'note',
        'source',
        'expense_item_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'category_id');
    }

    public function expenseItem(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class);
    }

    public function isSynced(): bool
    {
        return $this->source === self::SOURCE_BUDGET_PLANNER;
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_MANUAL);
    }

    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_BUDGET_PLANNER);
    }

    /** Rows shown on the overview/analysis screens: supplier visible AND category visible (or uncategorized). */
    public function scopeVisibleInOverview(Builder $query): Builder
    {
        return $query
            ->whereHas('supplier', fn (Builder $q) => $q->where('show_in_overview', true))
            ->where(fn (Builder $q) => $q
                ->whereNull('category_id')
                ->orWhereHas('category', fn (Builder $q2) => $q2->where('show_in_overview', true)));
    }
}
