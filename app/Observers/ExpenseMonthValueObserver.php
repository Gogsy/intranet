<?php

namespace App\Observers;

use App\Models\ExpenseMonthValue;
use App\Services\InvoiceTrackerSync;

/**
 * Mirrors expense month amounts into Invoice Tracker plan rows (see
 * InvoiceTrackerSync). mark_color-only saves are ignored — that's a payment
 * tracking flag, not a budget value.
 */
class ExpenseMonthValueObserver
{
    public function saved(ExpenseMonthValue $value): void
    {
        if ($value->wasRecentlyCreated || $value->wasChanged('amount')) {
            InvoiceTrackerSync::syncMonthValue($value);
        }
    }

    public function deleted(ExpenseMonthValue $value): void
    {
        InvoiceTrackerSync::removeMonthValue($value);
    }
}
