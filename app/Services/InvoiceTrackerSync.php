<?php

namespace App\Services;

use App\Models\BudgetYear;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\Supplier;
use App\Models\SupplierBudget;
use App\Models\SupplierCategory;
use Illuminate\Support\Facades\DB;

/**
 * One-way mirror Budget Planner -> Invoice Tracker (Option A: the planner is
 * the source of truth for PLANNED amounts, the tracker for ACTUAL invoices).
 *
 * Only the year's designated version (budget_years.tracker_source_version_id)
 * feeds the tracker. Mapping per expense item:
 *   vendor (free text, fallback: item name)  -> Supplier (find-or-create by name)
 *   item name                                -> SupplierCategory under that supplier
 *   each non-zero ExpenseMonthValue          -> SupplierBudget row keyed by
 *                                               (expense_item_id, year, month),
 *                                               source='budget_planner'
 * Synced rows are read-only in the tracker UI; manual rows coexist untouched.
 * Deleting a BudgetVersion cleans synced rows via DB FK cascade (no events
 * fire there) — everything else goes through these methods via observers.
 */
class InvoiceTrackerSync
{
    /** Full sync of one expense item: supplier/category links + all months. */
    public static function syncExpenseItem(ExpenseItem $item): void
    {
        $version = $item->budgetVersion;

        if ($version === null || ! $version->isTrackerSource()) {
            return;
        }

        DB::transaction(function () use ($item, $version) {
            [$supplier, $category] = self::ensureLinks($item);
            $year = $version->budgetYear->year;

            foreach ($item->monthValues()->get() as $value) {
                self::upsertMonth($item, $supplier, $category, $year, $value->month, (float) $value->amount);
            }

            // An item rename re-points to a new category; a vendor change to a
            // new supplier — refresh the denormalised columns on months that
            // did not change amount too.
            SupplierBudget::where('expense_item_id', $item->id)->update([
                'supplier_id' => $supplier->id,
                'category_id' => $category->id,
            ]);
        });
    }

    /** Cheap single-month path used by the ExpenseMonthValue observer. */
    public static function syncMonthValue(ExpenseMonthValue $value): void
    {
        $item = $value->expenseItem()->first();
        $version = $item?->budgetVersion;

        if ($version === null || ! $version->isTrackerSource()) {
            return;
        }

        DB::transaction(function () use ($item, $version, $value) {
            [$supplier, $category] = self::ensureLinks($item);

            self::upsertMonth($item, $supplier, $category, $version->budgetYear->year, $value->month, (float) $value->amount);
        });
    }

    public static function removeMonthValue(ExpenseMonthValue $value): void
    {
        SupplierBudget::where('expense_item_id', $value->expense_item_id)
            ->where('month', $value->month)
            ->delete();
    }

    /** Suppliers/categories stay (invoices may reference them); only the plan rows go. */
    public static function removeExpenseItem(ExpenseItem $item): void
    {
        SupplierBudget::where('expense_item_id', $item->id)->delete();
    }

    /** Wipe & rebuild the year's synced rows from its pointed version. */
    public static function resyncYear(BudgetYear $budgetYear): void
    {
        DB::transaction(function () use ($budgetYear) {
            SupplierBudget::synced()->forYear($budgetYear->year)->delete();

            $version = $budgetYear->trackerSourceVersion;

            if ($version === null) {
                return;
            }

            foreach ($version->expenseItems()->with('monthValues')->get() as $item) {
                self::syncExpenseItem($item);
            }
        });
    }

    /**
     * Resolve (find-or-create) the Supplier from the item's free-text vendor
     * (fallback: item name — every mirrored expense must land somewhere) and
     * the SupplierCategory from the item name, and store the supplier link
     * back on the expense item.
     *
     * @return array{0: Supplier, 1: SupplierCategory}
     */
    private static function ensureLinks(ExpenseItem $item): array
    {
        $supplierName = trim((string) $item->vendor) !== '' ? trim($item->vendor) : trim($item->name);

        // Mirrored suppliers default expected_monthly=false so the monthly
        // missing-invoice alert doesn't fire for one-time/annual items; the
        // tracker owner flips it on per supplier.
        $supplier = Supplier::firstOrCreate(
            ['name' => $supplierName],
            ['is_active' => true, 'expected_monthly' => false],
        );

        if ($item->supplier_id !== $supplier->id) {
            // Quiet + guard-free + timestamp-free: a link column, not a budget
            // value — must not recurse into observers, trip the lock guard,
            // nor bump updated_at on a production budget row.
            ExpenseItem::withoutLockGuard(function () use ($item, $supplier) {
                $item->timestamps = false;
                $item->supplier_id = $supplier->id;
                $item->saveQuietly();
                $item->timestamps = true;
            });
        }

        $category = SupplierCategory::firstOrCreate([
            'supplier_id' => $supplier->id,
            'name' => trim($item->name),
        ]);

        return [$supplier, $category];
    }

    /**
     * The planner materialises all 12 months including zeros — zero months are
     * skipped/removed here so they don't pollute the tracker's over/under flags.
     */
    private static function upsertMonth(
        ExpenseItem $item,
        Supplier $supplier,
        SupplierCategory $category,
        int $year,
        int $month,
        float $amount,
    ): void {
        if ($amount == 0.0) {
            SupplierBudget::where('expense_item_id', $item->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            return;
        }

        SupplierBudget::updateOrCreate(
            ['expense_item_id' => $item->id, 'year' => $year, 'month' => $month],
            [
                'supplier_id' => $supplier->id,
                'category_id' => $category->id,
                'amount' => $amount,
                'source' => SupplierBudget::SOURCE_BUDGET_PLANNER,
                'note' => 'Synced from '.$item->budgetVersion->name,
            ],
        );
    }
}
