<?php

use App\Exceptions\BudgetVersionLockedException;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\InvestmentItem;
use App\Models\InvestmentType;
use App\Models\User;
use App\Services\BudgetVersionService;

function budgetYearAndVersion(string $type = 'PLAN', string $status = 'DRAFT'): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $window = BudgetVersion::editableWindowFor($type);

    return BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => $type,
        'name' => "{$type} 2026",
        'editable_from_month' => $window['from'],
        'editable_to_month' => $window['to'],
        'status' => $status,
    ]);
}

it('allows edits on a DRAFT version', function () {
    $version = budgetYearAndVersion('PLAN', 'DRAFT');
    $type = InvestmentType::create(['name' => 'Hardware']);

    $investment = InvestmentItem::create([
        'budget_version_id' => $version->id,
        'month' => 5,
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
    ]);

    $investment->update(['quantity' => 2]);
    expect($investment->fresh()->quantity)->toBe('2.00');
});

it('blocks budget-defining investment field edits on a LOCKED version', function () {
    $version = budgetYearAndVersion('PLAN', 'DRAFT');
    $type = InvestmentType::create(['name' => 'Hardware']);

    $investment = InvestmentItem::create([
        'budget_version_id' => $version->id,
        'month' => 5,
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
    ]);

    $version->update(['status' => 'LOCKED', 'locked_at' => now()]);

    expect(fn () => $investment->update(['quantity' => 2]))
        ->toThrow(BudgetVersionLockedException::class);
});

it('still allows realization-only investment field edits on a LOCKED version', function () {
    $version = budgetYearAndVersion('PLAN', 'DRAFT');
    $type = InvestmentType::create(['name' => 'Hardware']);

    $investment = InvestmentItem::create([
        'budget_version_id' => $version->id,
        'month' => 5,
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
        'decision_status' => 'Proposed',
        'purchased' => false,
    ]);

    $version->update(['status' => 'LOCKED', 'locked_at' => now()]);

    $investment->update(['decision_status' => 'Approved', 'purchased' => true, 'realization_comment' => 'Bought it']);

    $fresh = $investment->fresh();
    expect($fresh->decision_status)->toBe('Approved');
    expect($fresh->purchased)->toBeTrue();
});

it('blocks all expense field/month edits on a LOCKED version (no realization exception)', function () {
    $version = budgetYearAndVersion('PLAN', 'DRAFT');

    $expense = ExpenseItem::create([
        'budget_version_id' => $version->id,
        'name' => 'Office365',
        'expense_type' => 'MONTHLY',
    ]);
    $value = ExpenseMonthValue::create(['expense_item_id' => $expense->id, 'month' => 3, 'amount' => 100]);

    $version->update(['status' => 'LOCKED', 'locked_at' => now()]);

    expect(fn () => $expense->update(['name' => 'Office 365 renamed']))
        ->toThrow(BudgetVersionLockedException::class);

    expect(fn () => $value->update(['amount' => 200]))
        ->toThrow(BudgetVersionLockedException::class);
});

it('re-allows edits after TEMPORARILY_UNLOCKED', function () {
    $version = budgetYearAndVersion('PLAN', 'LOCKED');
    $user = User::factory()->create();

    $unlocked = BudgetVersionService::unlock($version, 'Fix typo', $user);
    expect($unlocked->status)->toBe('TEMPORARILY_UNLOCKED');

    $type = InvestmentType::create(['name' => 'Hardware']);
    $investment = InvestmentItem::create([
        'budget_version_id' => $unlocked->id,
        'month' => 5,
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
    ]);

    $investment->update(['quantity' => 3]);
    expect($investment->fresh()->quantity)->toBe('3.00');
});

it('blocks edits to a month outside the editable window even when unlocked', function () {
    // FC1: editable months 3-12, month 1 is outside the window.
    $version = budgetYearAndVersion('FC1', 'DRAFT');
    $type = InvestmentType::create(['name' => 'Hardware']);

    expect(fn () => InvestmentItem::create([
        'budget_version_id' => $version->id,
        'month' => 1,
        'investment_type_id' => $type->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 500,
        'classification' => 'Asset',
    ]))->toThrow(BudgetVersionLockedException::class);
});

it('keeps the version name editable regardless of lock status', function () {
    $version = budgetYearAndVersion('PLAN', 'LOCKED');

    $version->update(['name' => 'PLAN 2026 — renamed scenario']);

    expect($version->fresh()->name)->toBe('PLAN 2026 — renamed scenario');
});

it('locks a DRAFT version via the service', function () {
    $version = budgetYearAndVersion('PLAN', 'DRAFT');

    $locked = BudgetVersionService::lock($version);

    expect($locked->status)->toBe('LOCKED');
    expect($locked->locked_at)->not->toBeNull();
});

it('refuses to lock an already-locked version', function () {
    $version = budgetYearAndVersion('PLAN', 'LOCKED');

    expect(fn () => BudgetVersionService::lock($version))->toThrow(RuntimeException::class);
});
