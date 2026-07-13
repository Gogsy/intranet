<?php

namespace App\Support\InvoiceTracker;

use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\SupplierBudget;
use App\Models\SupplierCategory;
use Illuminate\Support\Collection;

class YearOverview
{
    protected const UNCATEGORIZED_KEY = 0;

    public const UNCATEGORIZED_LABEL = 'Uncategorized';

    /**
     * Supplier × month matrix for a year, with a per-category breakdown under
     * each supplier. Cell flags: `missing` (supplier level only), `over`
     * (spent above the monthly budget), `under` (spent below it); an on-spot
     * month carries no flag.
     *
     * @return array{year: int, rows: array<int, array<string, mixed>>, columnTotals: array<int, float>, grandTotal: float}
     */
    public static function matrix(int $year): array
    {
        [$suppliers, $spent, $budget, $labels] = self::collect($year);

        // Months that have fully ended and therefore must have an entry.
        $closedMonths = match (true) {
            $year < now()->year => 12,
            $year > now()->year => 0,
            default => now()->month - 1,
        };

        $rows = [];
        $columnTotals = array_fill(1, 12, 0.0);

        foreach ($suppliers as $supplier) {
            $categories = [];

            foreach (self::categoryKeys($supplier->id, $spent, $budget, $labels) as $key) {
                $cells = [];
                $catTotal = 0.0;

                foreach (range(1, 12) as $month) {
                    $amount = $spent[$supplier->id][$key][$month] ?? null;
                    $monthBudget = $budget[$supplier->id][$key][$month] ?? null;

                    $cells[$month] = [
                        'amount' => $amount,
                        ...self::compareFlags($amount, $monthBudget),
                    ];

                    $catTotal += $amount ?? 0.0;
                }

                $categories[] = [
                    'label' => $labels[$key],
                    'cells' => $cells,
                    'total' => $catTotal,
                ];
            }

            $cells = [];
            $rowTotal = 0.0;

            foreach (range(1, 12) as $month) {
                $amount = self::monthSum($spent[$supplier->id] ?? [], $month);
                $monthBudget = self::monthSum($budget[$supplier->id] ?? [], $month);

                $cells[$month] = [
                    'amount' => $amount,
                    'missing' => $amount === null && $supplier->expected_monthly && $supplier->is_active && $month <= $closedMonths,
                    ...self::compareFlags($amount, $monthBudget),
                ];

                $rowTotal += $amount ?? 0.0;
                $columnTotals[$month] += $amount ?? 0.0;
            }

            $rows[] = [
                'supplier' => $supplier,
                'cells' => $cells,
                'total' => $rowTotal,
                'categories' => $categories,
            ];
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'columnTotals' => $columnTotals,
            'grandTotal' => array_sum($columnTotals),
        ];
    }

    /**
     * Budget vs. actual per supplier, with per-category rows under each supplier.
     *
     * @return array<int, array{supplier: string, budget_ytd: float, spent: float, delta_ytd: float, budget_year: float, used_pct: ?float, categories: array<int, array<string, mixed>>}>
     */
    public static function budgetVsActual(int $year): array
    {
        [$suppliers, $spent, $budget, $labels] = self::collect($year);

        $monthsElapsed = $year < now()->year ? 12 : now()->month;

        $rows = [];

        foreach ($suppliers as $supplier) {
            $categories = [];

            foreach (self::categoryKeys($supplier->id, $spent, $budget, $labels) as $key) {
                $categories[] = [
                    'label' => $labels[$key],
                    ...self::budgetFigures(
                        $spent[$supplier->id][$key] ?? [],
                        $budget[$supplier->id][$key] ?? [],
                        $monthsElapsed,
                    ),
                ];
            }

            $spentMonths = self::flattenMonths($spent[$supplier->id] ?? []);
            $budgetMonths = self::flattenMonths($budget[$supplier->id] ?? []);

            $rows[] = [
                'supplier' => $supplier->name,
                ...self::budgetFigures($spentMonths, $budgetMonths, $monthsElapsed),
                'categories' => $categories,
            ];
        }

        return $rows;
    }

    /**
     * Supplier + category detail rows across all suppliers for the Analysis page,
     * sorted by spend, with share of total and same-period previous-year change.
     *
     * @return array{year: int, grandTotal: float, rows: array<int, array<string, mixed>>}
     */
    public static function categoryDetail(int $year): array
    {
        [$suppliers, $spent, $budget, $labels] = self::collect($year);

        $comparableMonths = $year === now()->year ? now()->month : 12;

        $previous = Invoice::query()
            ->visibleInOverview()
            ->forYear($year - 1)
            ->where('month', '<=', $comparableMonths)
            ->selectRaw('supplier_id, category_id, SUM(amount) AS total')
            ->groupBy('supplier_id', 'category_id')
            ->get()
            ->groupBy('supplier_id')
            ->map(fn ($group) => $group->keyBy(fn ($row) => $row->category_id ?? self::UNCATEGORIZED_KEY));

        $grandTotal = 0.0;
        $rows = [];

        foreach ($suppliers as $supplier) {
            foreach (self::categoryKeys($supplier->id, $spent, $budget, $labels) as $key) {
                $months = $spent[$supplier->id][$key] ?? [];
                $total = array_sum($months);
                $budgetYear = array_sum($budget[$supplier->id][$key] ?? []);
                $prev = (float) ($previous[$supplier->id][$key]->total ?? 0);

                $grandTotal += $total;

                $rows[] = [
                    'supplier' => $supplier->name,
                    'category' => $labels[$key],
                    'spent' => $total,
                    'active_months' => count($months),
                    'budget_year' => $budgetYear,
                    'delta' => $budgetYear - $total,
                    'yoy_pct' => $prev > 0 ? ($total - $prev) / $prev * 100 : null,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['spent'] <=> $a['spent']);

        return [
            'year' => $year,
            'grandTotal' => $grandTotal,
            'rows' => $rows,
        ];
    }

    /**
     * Shared per-supplier, per-category monthly sums of invoices and budgets.
     *
     * @return array{0: Collection<int, Supplier>, 1: array<int, array<int, array<int, float>>>, 2: array<int, array<int, array<int, float>>>, 3: array<int, string>}
     */
    protected static function collect(int $year): array
    {
        $suppliers = Supplier::query()
            ->visibleInOverview()
            ->where(fn ($q) => $q
                ->where('is_active', true)
                ->orWhereHas('invoices', fn ($q) => $q->where('year', $year)))
            ->orderBy('name')
            ->get();

        $spent = self::monthlySums(
            Invoice::query()->forYear($year)->visibleInOverview()
                ->selectRaw('supplier_id, category_id, month, SUM(amount) AS total')
                ->groupBy('supplier_id', 'category_id', 'month')
                ->get(),
        );

        $budget = self::monthlySums(
            SupplierBudget::query()->forYear($year)->visibleInOverview()
                ->selectRaw('supplier_id, category_id, month, SUM(amount) AS total')
                ->groupBy('supplier_id', 'category_id', 'month')
                ->get(),
        );

        $labels = SupplierCategory::query()->pluck('name', 'id')->all();
        $labels[self::UNCATEGORIZED_KEY] = self::UNCATEGORIZED_LABEL;

        return [$suppliers, $spent, $budget, $labels];
    }

    /**
     * @return array<int, array<int, array<int, float>>>
     */
    protected static function monthlySums(Collection $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $key = $row->category_id ?? self::UNCATEGORIZED_KEY;
            $map[$row->supplier_id][$key][$row->month] = (float) $row->total;
        }

        return $map;
    }

    /**
     * Category keys for a supplier that have any spend or budget, sorted by
     * label with the uncategorized bucket last.
     *
     * @param  array<int, array<int, array<int, float>>>  $spent
     * @param  array<int, array<int, array<int, float>>>  $budget
     * @param  array<int, string>  $labels
     * @return array<int, int>
     */
    protected static function categoryKeys(int $supplierId, array $spent, array $budget, array $labels): array
    {
        $keys = array_unique(array_merge(
            array_keys($spent[$supplierId] ?? []),
            array_keys($budget[$supplierId] ?? []),
        ));

        usort($keys, function (int $a, int $b) use ($labels): int {
            if ($a === self::UNCATEGORIZED_KEY || $b === self::UNCATEGORIZED_KEY) {
                return $a === self::UNCATEGORIZED_KEY ? 1 : -1;
            }

            return strcasecmp($labels[$a] ?? '', $labels[$b] ?? '');
        });

        return $keys;
    }

    /**
     * @return array{over: bool, under: bool}
     */
    protected static function compareFlags(?float $amount, ?float $budget): array
    {
        if ($amount === null || $budget === null) {
            return ['over' => false, 'under' => false];
        }

        $amount = round($amount, 2);
        $budget = round($budget, 2);

        return [
            'over' => $amount > $budget,
            'under' => $amount < $budget,
        ];
    }

    /**
     * Sum one month across all categories; null when no category has a value.
     *
     * @param  array<int, array<int, float>>  $categories
     */
    protected static function monthSum(array $categories, int $month): ?float
    {
        $values = array_filter(array_map(fn (array $months) => $months[$month] ?? null, $categories), fn ($v) => $v !== null);

        return $values === [] ? null : array_sum($values);
    }

    /**
     * Merge per-category month maps into a single month => total map.
     *
     * @param  array<int, array<int, float>>  $categories
     * @return array<int, float>
     */
    protected static function flattenMonths(array $categories): array
    {
        $months = [];

        foreach ($categories as $categoryMonths) {
            foreach ($categoryMonths as $month => $value) {
                $months[$month] = ($months[$month] ?? 0.0) + $value;
            }
        }

        return $months;
    }

    /**
     * @param  array<int, float>  $spentMonths
     * @param  array<int, float>  $budgetMonths
     * @return array{budget_ytd: float, spent: float, delta_ytd: float, budget_year: float, used_pct: ?float}
     */
    protected static function budgetFigures(array $spentMonths, array $budgetMonths, int $monthsElapsed): array
    {
        $spent = array_sum($spentMonths);
        $budgetYear = array_sum($budgetMonths);
        $budgetYtd = array_sum(array_filter($budgetMonths, fn (int $month) => $month <= $monthsElapsed, ARRAY_FILTER_USE_KEY));

        return [
            'budget_ytd' => $budgetYtd,
            'spent' => $spent,
            'delta_ytd' => $budgetYtd - $spent,
            'budget_year' => $budgetYear,
            'used_pct' => $budgetYear > 0 ? $spent / $budgetYear * 100 : null,
        ];
    }
}
