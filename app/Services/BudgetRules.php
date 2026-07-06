<?php

namespace App\Services;

/**
 * Pure budget calculation rules — no DB access, so these are trivially unit
 * testable and reused identically by the create flow, the expense-generator
 * action, and the Excel importer.
 */
class BudgetRules
{
    /** Editable month window (1-12) for each version type, computed once at version creation. */
    public static function editableWindowFor(string $type): array
    {
        return match ($type) {
            'FC1' => ['from' => 3, 'to' => 12],
            'FC2' => ['from' => 7, 'to' => 12],
            default => ['from' => 1, 'to' => 12], // PLAN
        };
    }

    public static function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    public static function investmentTotal(float $quantity, float $unitNetPrice): float
    {
        return self::roundMoney($quantity * $unitNetPrice);
    }

    /** @param array<int, float> $monthAmounts amount keyed by month (1-12) */
    public static function expenseTotal(array $monthAmounts): float
    {
        return self::roundMoney(array_sum($monthAmounts));
    }

    /**
     * Generates the 12 monthly amounts for an expense, based on its entry
     * mode. The version's editable month window deliberately does NOT apply
     * to expenses (it is an investment concept) — all 12 months are filled.
     * ANNUAL_AVR divides the annual amount across months 1-11 (floored to
     * cents) and puts the exact remainder in month 12, so the sum always
     * equals the entered annual amount regardless of rounding.
     *
     * @return array<int, float> amount keyed by month (1-12)
     */
    public static function generateExpenseMonths(string $type, float $amount, int $selectedMonth = 1): array
    {
        $months = range(1, 12);

        return match ($type) {
            'ONE_TIME' => collect($months)->mapWithKeys(
                fn ($month) => [$month => $month === $selectedMonth ? self::roundMoney($amount) : 0.0]
            )->all(),

            'ANNUAL_AVR' => self::generateAnnualAvr($amount),

            'MONTHLY' => collect($months)->mapWithKeys(
                fn ($month) => [$month => self::roundMoney($amount)]
            )->all(),

            // VOLUME: manual entry per month, starts at zero.
            default => collect($months)->mapWithKeys(fn ($month) => [$month => 0.0])->all(),
        };
    }

    private static function generateAnnualAvr(float $amount): array
    {
        $monthly = floor(($amount / 12) * 100) / 100;
        $result = [];
        $assigned = 0.0;

        foreach (range(1, 12) as $month) {
            if ($month === 12) {
                $result[$month] = self::roundMoney($amount - $assigned);
                continue;
            }
            $assigned = self::roundMoney($assigned + $monthly);
            $result[$month] = $monthly;
        }

        return $result;
    }
}
