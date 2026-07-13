<?php

namespace App\Observers;

use App\Models\ExpenseItem;
use App\Services\InvoiceTrackerSync;

/**
 * Mirrors Budget Planner expense items into the Invoice Tracker (see
 * InvoiceTrackerSync). Fires on every persistence path — UI grid, Excel
 * import, version copy — the sync itself bails when the item's version is
 * not the year's tracker source.
 */
class ExpenseItemObserver
{
    public function created(ExpenseItem $item): void
    {
        InvoiceTrackerSync::syncExpenseItem($item);
    }

    public function updated(ExpenseItem $item): void
    {
        // Only name (-> category), vendor (-> supplier) and version moves
        // matter to the tracker; comment/description/account_code edits and
        // the sync's own supplier_id write-back don't re-sync.
        if ($item->wasChanged(['name', 'vendor', 'budget_version_id'])) {
            InvoiceTrackerSync::syncExpenseItem($item);
        }
    }

    public function deleted(ExpenseItem $item): void
    {
        InvoiceTrackerSync::removeExpenseItem($item);
    }
}
