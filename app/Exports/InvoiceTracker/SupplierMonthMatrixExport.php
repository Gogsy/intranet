<?php

namespace App\Exports\InvoiceTracker;

use App\Support\InvoiceTracker\Months;
use App\Support\InvoiceTracker\YearOverview;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Suppliers × months matrix with the same cell colouring as the widget:
 * red = missing entry, amber = over monthly budget, green = under it.
 */
class SupplierMonthMatrixExport implements FromArray, WithHeadings, WithStyles
{
    protected const FILL_MISSING = 'FFF4CCCC';

    protected const FILL_OVER = 'FFFCE5CD';

    protected const FILL_UNDER = 'FFD9EAD3';

    /** @var array<string, string> cell coordinate => ARGB fill */
    protected array $fills = [];

    public function __construct(protected int $year)
    {
    }

    public function headings(): array
    {
        return [
            'Supplier / category',
            ...array_map(fn (int $m) => Months::shortName($m), range(1, 12)),
            'Total',
        ];
    }

    public function array(): array
    {
        $matrix = YearOverview::matrix($this->year);

        $rows = [];
        $rowIndex = 2; // 1 = headings

        foreach ($matrix['rows'] as $row) {
            $cells = [$row['supplier']->name . ($row['supplier']->is_active ? '' : ' (inactive)')];

            foreach ($row['cells'] as $month => $cell) {
                $cells[] = $cell['amount'];
                $this->rememberFill($rowIndex, $month, $cell);
            }

            $cells[] = $row['total'];
            $rows[] = $cells;
            $rowIndex++;

            foreach ($row['categories'] as $category) {
                $cells = ['    ' . $category['label']];

                foreach ($category['cells'] as $month => $cell) {
                    $cells[] = $cell['amount'];
                    $this->rememberFill($rowIndex, $month, $cell);
                }

                $cells[] = $category['total'];
                $rows[] = $cells;
                $rowIndex++;
            }
        }

        $rows[] = [
            'Total',
            ...array_values($matrix['columnTotals']),
            $matrix['grandTotal'],
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->fills as $coordinate => $argb) {
            $sheet->getStyle($coordinate)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($argb);
        }

        $lastRow = $sheet->getHighestRow();

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }

    /** @param array{amount: ?float, over: bool, under: bool, missing?: bool} $cell */
    protected function rememberFill(int $rowIndex, int $month, array $cell): void
    {
        $argb = match (true) {
            $cell['missing'] ?? false => self::FILL_MISSING,
            $cell['over'] => self::FILL_OVER,
            $cell['under'] => self::FILL_UNDER,
            default => null,
        };

        if ($argb === null) {
            return;
        }

        // Month columns start at B (column 1 = names).
        $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month + 1);
        $this->fills["{$column}{$rowIndex}"] = $argb;
    }
}
