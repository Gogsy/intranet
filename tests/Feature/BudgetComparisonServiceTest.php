<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Services\BudgetComparisonService;
use App\Services\BudgetVersionService;

it('classifies added, removed, changed, and unchanged rows', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $plan = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware']);

    $unchanged = $plan->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Monitor',
        'quantity' => 1, 'unit_net_price' => 150, 'classification' => 'Asset',
    ]);
    $toBeChanged = $plan->investmentItems()->create([
        'month' => 4, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);
    $toBeRemoved = $plan->investmentItems()->create([
        'month' => 5, 'investment_type_id' => $type->id, 'description' => 'Printer',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
    ]);

    $fc1 = BudgetVersionService::createFromTemplate($year, 'FC1', $plan);

    // Change the laptop's quantity in FC1, remove the printer, add a new row.
    $fc1->investmentItems()->where('origin_id', $toBeChanged->origin_id)->first()->update(['quantity' => 2]);
    $fc1->investmentItems()->where('origin_id', $toBeRemoved->origin_id)->first()->delete();
    $fc1->investmentItems()->create([
        'month' => 6, 'investment_type_id' => $type->id, 'description' => 'New scanner',
        'quantity' => 1, 'unit_net_price' => 200, 'classification' => 'Asset',
    ]);

    $rows = BudgetComparisonService::compare($plan, $fc1)->keyBy('origin_id');

    expect($rows[$unchanged->origin_id]['status'])->toBe('unchanged');
    expect($rows[$unchanged->origin_id]['difference'])->toBe(0.0);

    expect($rows[$toBeChanged->origin_id]['status'])->toBe('changed');
    expect($rows[$toBeChanged->origin_id]['old_total'])->toBe(400.0);
    expect($rows[$toBeChanged->origin_id]['new_total'])->toBe(800.0);
    expect($rows[$toBeChanged->origin_id]['difference'])->toBe(400.0);
    expect($rows[$toBeChanged->origin_id]['percentage_difference'])->toBe(100.0);

    expect($rows[$toBeRemoved->origin_id]['status'])->toBe('removed');
    expect($rows[$toBeRemoved->origin_id]['new_total'])->toBe(0.0);

    $addedRow = $rows->first(fn ($row) => $row['label'] === 'New scanner');
    expect($addedRow['status'])->toBe('added');
    expect($addedRow['old_total'])->toBe(0.0);
    expect($addedRow['percentage_difference'])->toBeNull();
});

it('filters comparison rows by vendor, account code, category and month', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $plan = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);

    $plan->expenseItems()->create([
        'name' => 'Office365', 'vendor' => 'Microsoft', 'account_code' => '7760010', 'expense_type' => 'MONTHLY',
    ]);
    $plan->expenseItems()->create([
        'name' => 'AWS', 'vendor' => 'Amazon', 'account_code' => '7760010', 'expense_type' => 'MONTHLY',
    ]);

    $fc1 = BudgetVersionService::createFromTemplate($year, 'FC1', $plan);

    $rows = BudgetComparisonService::compare($plan, $fc1);

    expect($rows->where('vendor', 'Microsoft'))->toHaveCount(1);
    expect($rows->where('account_code', '7760010'))->toHaveCount(2);
    expect($rows->where('category', 'AWS'))->toHaveCount(1);
});
