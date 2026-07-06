<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\ExpenseItem;
use App\Models\InvestmentType;
use App\Services\BudgetVersionService;

function makeTemplateVersion(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $plan = BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'PLAN',
        'name' => 'Plan 2026',
        'editable_from_month' => 1,
        'editable_to_month' => 12,
        'status' => 'DRAFT',
    ]);

    $type = InvestmentType::create(['name' => 'Hardware']);

    $plan->investmentItems()->create([
        'month' => 2, // outside FC1's 3-12 window — copy must still succeed
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
        'decision_status' => 'Approved',
        'purchased' => true,
        'realization_comment' => 'Bought in January',
    ]);

    $expense = $plan->expenseItems()->create([
        'name' => 'Office365',
        'expense_type' => 'MONTHLY',
    ]);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }

    return $plan->fresh(['investmentItems', 'expenseItems.monthValues']);
}

it('copies template data into a new version and preserves origin lineage', function () {
    $plan = makeTemplateVersion();
    $sourceInvestment = $plan->investmentItems->first();
    $sourceExpense = $plan->expenseItems->first();

    $fc1 = BudgetVersionService::createFromTemplate($plan->budgetYear, 'FC1', $plan);

    expect($fc1->editable_from_month)->toBe(3);
    expect($fc1->editable_to_month)->toBe(12);
    expect($fc1->baseline_version_id)->toBe($plan->id);

    $copiedInvestment = $fc1->investmentItems()->first();
    expect($copiedInvestment->origin_id)->toBe($sourceInvestment->origin_id);
    expect($copiedInvestment->month)->toBe(2); // copied uncritically even though outside FC1's window

    $copiedExpense = $fc1->expenseItems()->first();
    expect($copiedExpense->origin_id)->toBe($sourceExpense->origin_id);
    expect($copiedExpense->monthValues()->count())->toBe(12);
    expect($copiedExpense->total)->toBe(1200.0);
});

it('preserves realization fields when copying within the same budget year', function () {
    $plan = makeTemplateVersion();

    $fc1 = BudgetVersionService::createFromTemplate($plan->budgetYear, 'FC1', $plan);
    $copiedInvestment = $fc1->investmentItems()->first();

    expect($copiedInvestment->decision_status)->toBe('Approved');
    expect($copiedInvestment->purchased)->toBeTrue();
    expect($copiedInvestment->realization_comment)->toBe('Bought in January');
});

it('resets realization fields when copying into a new budget year', function () {
    $plan = makeTemplateVersion();
    $year2027 = BudgetYear::create(['year' => 2027, 'name' => 'IT Budget 2027', 'status' => 'ACTIVE']);

    $plan2027 = BudgetVersionService::createFromTemplate($year2027, 'PLAN', $plan);
    $copiedInvestment = $plan2027->investmentItems()->first();

    expect($copiedInvestment->decision_status)->toBe('Proposed');
    expect($copiedInvestment->purchased)->toBeFalse();
    expect($copiedInvestment->realization_comment)->toBe('');
});

it('creates an empty version when no template is given', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    $version = BudgetVersionService::createFromTemplate($year, 'PLAN');

    expect($version->investmentItems)->toHaveCount(0);
    expect($version->expenseItems)->toHaveCount(0);
    expect($version->baseline_version_id)->toBeNull();
});

it('allows multiple versions of the same type to coexist in one budget year', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    $fc1a = BudgetVersionService::createFromTemplate($year, 'FC1');
    $fc1b = BudgetVersionService::createFromTemplate($year, 'FC1');

    expect($fc1a->id)->not->toBe($fc1b->id);
    expect($year->versions()->where('type', 'FC1')->count())->toBe(2);
});
