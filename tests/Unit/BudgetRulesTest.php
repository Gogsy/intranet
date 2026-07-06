<?php

use App\Services\BudgetRules;

it('computes editable windows per version type', function () {
    expect(BudgetRules::editableWindowFor('PLAN'))->toBe(['from' => 1, 'to' => 12]);
    expect(BudgetRules::editableWindowFor('FC1'))->toBe(['from' => 3, 'to' => 12]);
    expect(BudgetRules::editableWindowFor('FC2'))->toBe(['from' => 7, 'to' => 12]);
});

it('rounds money to 2 decimals', function () {
    expect(BudgetRules::roundMoney(1.005))->toBe(1.01);
    expect(BudgetRules::roundMoney(10))->toBe(10.0);
});

it('computes investment totals', function () {
    expect(BudgetRules::investmentTotal(3, 45.5))->toBe(136.5);
});

it('computes expense totals from month amounts', function () {
    expect(BudgetRules::expenseTotal([1 => 100, 2 => 200.5, 3 => 0]))->toBe(300.5);
});

it('generates one-time expense months with the full amount in the selected month', function () {
    $months = BudgetRules::generateExpenseMonths('ONE_TIME', 5000, 4);

    expect($months[4])->toBe(5000.0);
    expect(collect($months)->except([4])->filter(fn ($amount) => $amount !== 0.0))->toHaveCount(0);
});

it('generates monthly expense months by repeating the amount', function () {
    $months = BudgetRules::generateExpenseMonths('MONTHLY', 100);

    expect(collect($months)->unique()->values()->all())->toBe([100.0]);
});

it('generates volume expense months starting at zero', function () {
    $months = BudgetRules::generateExpenseMonths('VOLUME', 999);

    expect(collect($months)->unique()->values()->all())->toBe([0.0]);
});

it('generates annual AVR expense months whose sum exactly equals the input amount', function () {
    $months = BudgetRules::generateExpenseMonths('ANNUAL_AVR', 3500);

    expect(round(array_sum($months), 2))->toBe(3500.0);
    // months 1-11 get the floored monthly amount, month 12 absorbs the remainder
    expect($months[1])->toBe(291.66);
    foreach (range(1, 11) as $month) {
        expect($months[$month])->toBe(291.66);
    }
    expect($months[12])->toBeGreaterThan(0);
});

it('handles annual AVR amounts that do not divide evenly, still summing exactly', function () {
    $months = BudgetRules::generateExpenseMonths('ANNUAL_AVR', 100);

    expect(round(array_sum($months), 2))->toBe(100.0);
});

it('always fills all 12 months — the editable window is an investment concept, not an expense one', function () {
    $months = BudgetRules::generateExpenseMonths('MONTHLY', 100);

    expect($months)->toHaveCount(12);
    foreach (range(1, 12) as $month) {
        expect($months[$month])->toBe(100.0);
    }
});
