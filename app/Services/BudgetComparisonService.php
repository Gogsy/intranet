<?php

namespace App\Services;

use Closure;
use App\Models\BudgetVersion;
use Illuminate\Support\Collection;

/**
 * Cross-version comparison — a port of the original app's comparisonService.ts.
 * Groups investments/expenses by origin_id (lineage across versions) and
 * classifies each origin as added/removed/changed/unchanged.
 */
class BudgetComparisonService
{
    public static function compare(BudgetVersion $old, BudgetVersion $new): Collection
    {
        $investmentRows = self::compareGroup(
            $old->investmentItems()->with('investmentType')->get(),
            $new->investmentItems()->with('investmentType')->get(),
            fn ($item) => $item->origin_id,
            fn ($item) => $item->total,
            fn ($item) => [
                'label' => $item->description,
                'category' => $item->investmentType?->name,
                'vendor' => null,
                'account_code' => null,
                'month' => $item->month,
            ],
            'investment',
        );

        $expenseRows = self::compareGroup(
            $old->expenseItems()->with('monthValues')->get(),
            $new->expenseItems()->with('monthValues')->get(),
            fn ($item) => $item->origin_id,
            fn ($item) => $item->total,
            fn ($item) => [
                'label' => $item->description ?: $item->name,
                'category' => $item->name,
                'vendor' => $item->vendor,
                'account_code' => $item->account_code,
                'month' => null,
            ],
            'expense',
        );

        return $investmentRows->concat($expenseRows)->values();
    }

    /**
     * @param  Collection  $oldItems
     * @param  Collection  $newItems
     */
    private static function compareGroup(
        Collection $oldItems,
        Collection $newItems,
        Closure $getOrigin,
        Closure $getTotal,
        Closure $getMeta,
        string $kind,
    ): Collection {
        $originIds = $oldItems->map($getOrigin)->concat($newItems->map($getOrigin))->unique();

        return $originIds->map(function ($originId) use ($oldItems, $newItems, $getOrigin, $getTotal, $getMeta, $kind) {
            $oldItem = $oldItems->first(fn ($item) => $getOrigin($item) === $originId);
            $newItem = $newItems->first(fn ($item) => $getOrigin($item) === $originId);

            $oldTotal = $oldItem ? (float) $getTotal($oldItem) : 0.0;
            $newTotal = $newItem ? (float) $getTotal($newItem) : 0.0;
            $difference = BudgetRules::roundMoney($newTotal - $oldTotal);

            $status = match (true) {
                ! $oldItem => 'added',
                ! $newItem => 'removed',
                $difference === 0.0 => 'unchanged',
                default => 'changed',
            };

            $meta = $getMeta($newItem ?? $oldItem);

            return array_merge([
                'origin_id' => $originId,
                'kind' => $kind,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'difference' => $difference,
                'percentage_difference' => $oldTotal == 0.0 ? null : BudgetRules::roundMoney(($difference / $oldTotal) * 100),
                'status' => $status,
            ], $meta);
        })->values();
    }
}
