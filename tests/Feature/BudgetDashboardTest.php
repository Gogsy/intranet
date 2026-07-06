<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;

it('computes version totals across investments and expenses', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware']);

    $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 2, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);
    $expense = $version->expenseItems()->create(['name' => 'Office365', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }

    $fresh = $version->fresh(['investmentItems', 'expenseItems.monthValues']);

    expect($fresh->totalInvestments())->toBe(800.0);
    expect($fresh->totalExpenses())->toBe(1200.0);
    expect($fresh->total())->toBe(2000.0);
});

it('flags investments purchased without approval as a data-quality issue', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware']);

    $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Approved and purchased',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
        'decision_status' => 'Approved', 'purchased' => true,
    ]);
    $version->investmentItems()->create([
        'month' => 4, 'investment_type_id' => $type->id, 'description' => 'Purchased without approval',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
        'decision_status' => 'Proposed', 'purchased' => true,
    ]);
    $version->investmentItems()->create([
        'month' => 5, 'investment_type_id' => $type->id, 'description' => 'Approved, not yet purchased',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
        'decision_status' => 'Approved', 'purchased' => false,
    ]);

    $summary = $version->fresh(['investmentItems'])->investmentSummary();

    expect($summary['approved'])->toBe(2);
    expect($summary['purchased'])->toBe(2);
    expect($summary['purchasedWithoutApproval'])->toBe(1);
});

it('computes monthly totals for the chart widget', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware']);

    $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 500, 'classification' => 'Asset',
    ]);
    $expense = $version->expenseItems()->create(['name' => 'Office365', 'expense_type' => 'ONE_TIME']);
    $expense->monthValues()->create(['month' => 3, 'amount' => 250]);
    foreach (array_diff(range(1, 12), [3]) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 0]);
    }

    $totals = $version->fresh(['investmentItems', 'expenseItems.monthValues'])->monthlyTotals();

    expect($totals[3]['investments'])->toBe(500.0);
    expect($totals[3]['expenses'])->toBe(250.0);
    expect($totals[1]['investments'])->toBe(0.0);
    expect($totals[1]['expenses'])->toBe(0.0);
});
