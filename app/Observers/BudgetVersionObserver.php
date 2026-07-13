<?php

namespace App\Observers;

use App\Models\BudgetVersion;

/**
 * Auto-points a year's Invoice Tracker source at its FIRST version, so the
 * naive flow "create budget -> add expense -> it appears in the tracker"
 * works with zero extra clicks. The pointer never moves automatically after
 * that — switching to FC1/FC2 is an explicit "Use for Invoice Tracker"
 * action (forecasts overwrite the plan, that's a decision, not a default).
 */
class BudgetVersionObserver
{
    public function created(BudgetVersion $version): void
    {
        $year = $version->budgetYear;

        if ($year !== null && $year->tracker_source_version_id === null) {
            $year->update(['tracker_source_version_id' => $version->id]);
        }
    }
}
