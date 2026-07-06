<?php

namespace App\Support;

/**
 * Fixed option lists for the Budget Planner module. Unlike Investment Type
 * (a manageable lookup, since the real templates show that list growing ad
 * hoc), these are stable accounting/technical concepts that don't need a
 * runtime-editable lookup table.
 */
class BudgetPlannerOptions
{
    public const VERSION_TYPES = ['PLAN' => 'Plan', 'FC1' => 'FC1', 'FC2' => 'FC2'];

    public const VERSION_STATUSES = [
        'DRAFT' => 'Draft',
        'LOCKED' => 'Locked',
        'TEMPORARILY_UNLOCKED' => 'Temporarily unlocked',
        'ARCHIVED' => 'Archived',
    ];

    public const CLASSIFICATIONS = [
        'Asset' => 'Asset',
        'Consumable' => 'Consumable',
        'Rent' => 'Rent',
    ];

    public const INVESTMENT_DECISION_STATUSES = [
        'Proposed' => 'Proposed',
        'Go' => 'Go',
        'No Go' => 'No Go',
        'Approved' => 'Approved',
        'Rejected' => 'Rejected',
        'Deferred' => 'Deferred',
    ];

    /**
     * Colours a month cell can be marked with (right-click menu on the
     * expenses grid). Keys are stored in expense_month_values.mark_color;
     * the CSS classes live in planner-tools.blade.php.
     */
    public const MARK_COLORS = ['green', 'yellow', 'red', 'blue', 'purple'];

    public const EXPENSE_TYPES = [
        'MONTHLY' => 'Monthly',
        'ONE_TIME' => 'One-time',
        'ANNUAL_AVR' => 'Annual (AVR)',
        'VOLUME' => 'Volume',
    ];
}
