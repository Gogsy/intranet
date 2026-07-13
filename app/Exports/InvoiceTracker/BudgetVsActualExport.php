<?php

namespace App\Exports\InvoiceTracker;

use App\Support\InvoiceTracker\YearOverview;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetVsActualExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(protected int $year)
    {
    }

    public function headings(): array
    {
        return ['Supplier / category', 'Budget YTD', 'Spent', 'Δ YTD', 'Budget (year)', 'Used %'];
    }

    public function array(): array
    {
        $rows = [];

        foreach (YearOverview::budgetVsActual($this->year) as $row) {
            $rows[] = [
                $row['supplier'],
                $row['budget_ytd'],
                $row['spent'],
                $row['delta_ytd'],
                $row['budget_year'],
                $row['used_pct'] !== null ? round($row['used_pct'], 1) : null,
            ];

            foreach ($row['categories'] as $category) {
                $rows[] = [
                    '    ' . $category['label'],
                    $category['budget_ytd'],
                    $category['spent'],
                    $category['delta_ytd'],
                    $category['budget_year'],
                    $category['used_pct'] !== null ? round($category['used_pct'], 1) : null,
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
