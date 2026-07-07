<?php

namespace App\Http\Controllers;

use App\Models\BudgetVersion;
use App\Models\ExpenseMonthValue;
use App\Support\BudgetPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Off-Livewire heartbeat endpoint for the Budget Planner grids.
 *
 * Does double duty, both deliberately OUT of Livewire so nothing here ever
 * re-renders the planner component (which would disable its buttons / morph its
 * filter dropdown — see {@see BudgetPresence}):
 *
 *  1. Live presence — who else is on this budget version and which row.
 *  2. A cheap "data fingerprint" so the client can detect when the underlying
 *     rows actually changed and refresh the table only then (and only while the
 *     user is idle), instead of Filament's every-3s blanket ->poll().
 *
 * Same authorization posture as the old Livewire method: any authenticated
 * panel user (the data is just names + an opaque change token).
 */
class BudgetPresenceController extends Controller
{
    public function update(Request $request, BudgetVersion $version): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([], 401);
        }

        $tab = $request->string('tab')->toString();

        // Only the two known grids; anything else is treated as "just viewing".
        if (! in_array($tab, ['expenseItems', 'investmentItems'], true)) {
            $tab = 'expenseItems';
        }

        $row = $request->input('row');

        $users = BudgetPresence::heartbeat(
            $version->getKey(),
            $user->id,
            $user->name,
            $tab,
            is_string($row) ? $row : null,
        );

        return response()->json([
            'users' => $users,
            'fingerprint' => $this->fingerprint($version),
        ]);
    }

    /**
     * A compact token that changes whenever any expense/investment row (or an
     * expense's month value) on this version is created, edited or deleted.
     * Built from row counts + latest updated_at timestamps — a few cheap
     * aggregate queries, no row payload.
     */
    protected function fingerprint(BudgetVersion $version): string
    {
        $expenseItemIds = $version->expenseItems()->select('id');

        return implode('|', [
            $version->expenseItems()->count(),
            $version->expenseItems()->max('updated_at'),
            ExpenseMonthValue::whereIn('expense_item_id', $expenseItemIds)->count(),
            ExpenseMonthValue::whereIn('expense_item_id', $expenseItemIds)->max('updated_at'),
            $version->investmentItems()->count(),
            $version->investmentItems()->max('updated_at'),
        ]);
    }
}
