<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\InvoiceTracker\Months;
use App\Support\InvoiceTracker\YearOverview;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function matrix(Request $request): BinaryFileResponse
    {
        $year = $this->year($request);
        $data = YearOverview::matrix($year);

        $header = (new Style())->setFontBold()->setBackgroundColor('F58220')->setFontColor(Color::WHITE);
        $bold = (new Style())->setFontBold();
        $missing = (new Style())->setBackgroundColor('FECACA');
        $over = (new Style())->setBackgroundColor('FDE68A')->setFontBold();
        $under = (new Style())->setBackgroundColor('D1FAE5');

        $cellStyle = fn (array $cell): ?Style => match (true) {
            $cell['missing'] ?? false => $missing,
            $cell['over'] => $over,
            $cell['under'] => $under,
            default => null,
        };

        return $this->write("suppliers-months-{$year}", function (Writer $writer) use ($data, $header, $bold, $cellStyle): void {
            $writer->addRow(Row::fromValues(
                ['Supplier / category', ...array_values(Months::options()), 'Total'],
                $header,
            ));

            foreach ($data['rows'] as $row) {
                $cells = [Cell::fromValue($row['supplier']->name, $bold)];

                foreach ($row['cells'] as $cell) {
                    $cells[] = Cell::fromValue($cell['amount'] ?? '', $cellStyle($cell));
                }

                $cells[] = Cell::fromValue($row['total'], $bold);
                $writer->addRow(new Row($cells));

                foreach ($row['categories'] as $category) {
                    $cells = [Cell::fromValue('    '.$category['label'])];

                    foreach ($category['cells'] as $cell) {
                        $cells[] = Cell::fromValue($cell['amount'] ?? '', $cellStyle($cell));
                    }

                    $cells[] = Cell::fromValue($category['total']);
                    $writer->addRow(new Row($cells));
                }
            }

            $writer->addRow(Row::fromValues(
                ['Total', ...array_values($data['columnTotals']), $data['grandTotal']],
                $bold,
            ));
        });
    }

    public function budgetVsActual(Request $request): BinaryFileResponse
    {
        $year = $this->year($request);
        $rows = YearOverview::budgetVsActual($year);

        $header = (new Style())->setFontBold()->setBackgroundColor('F58220')->setFontColor(Color::WHITE);
        $negative = (new Style())->setFontColor(Color::RED)->setFontBold();

        $bold = (new Style())->setFontBold();

        return $this->write("budget-vs-actual-{$year}", function (Writer $writer) use ($rows, $header, $bold, $negative): void {
            $writer->addRow(Row::fromValues(
                ['Supplier / category', 'Budget YTD', 'Spent', 'Delta YTD', 'Budget (year)', 'Used %'],
                $header,
            ));

            $figureCells = fn (array $row, ?Style $style): array => [
                Cell::fromValue($row['budget_ytd'], $style),
                Cell::fromValue($row['spent'], $style),
                Cell::fromValue($row['delta_ytd'], $row['delta_ytd'] < 0 ? $negative : $style),
                Cell::fromValue($row['budget_year'], $style),
                Cell::fromValue($row['used_pct'] !== null ? round($row['used_pct']).'%' : 'no budget', $style),
            ];

            foreach ($rows as $row) {
                $writer->addRow(new Row([
                    Cell::fromValue($row['supplier'], $bold),
                    ...$figureCells($row, $bold),
                ]));

                foreach ($row['categories'] as $category) {
                    $writer->addRow(new Row([
                        Cell::fromValue('    '.$category['label']),
                        ...$figureCells($category, null),
                    ]));
                }
            }
        });
    }

    public function invoices(): BinaryFileResponse
    {
        $header = (new Style())->setFontBold()->setBackgroundColor('F58220')->setFontColor(Color::WHITE);

        return $this->write('invoices', function (Writer $writer) use ($header): void {
            $writer->addRow(Row::fromValues(
                ['Supplier', 'Category', 'Year', 'Month', 'Amount (EUR)', 'SAP reference', 'Note', 'Entered at'],
                $header,
            ));

            Invoice::query()
                ->with(['supplier', 'category'])
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->orderBy('supplier_id')
                ->chunk(500, function ($invoices) use ($writer): void {
                    foreach ($invoices as $invoice) {
                        $writer->addRow(Row::fromValues([
                            $invoice->supplier->name,
                            $invoice->category?->name ?? 'Uncategorized',
                            $invoice->year,
                            Months::name($invoice->month),
                            (float) $invoice->amount,
                            $invoice->sap_reference,
                            $invoice->note,
                            $invoice->created_at->format('d.m.Y H:i'),
                        ]));
                    }
                });
        });
    }

    protected function year(Request $request): int
    {
        return (int) $request->query('year', (string) now()->year);
    }

    /**
     * @param  callable(Writer): void  $build
     */
    protected function write(string $name, callable $build): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        $writer = new Writer();
        $writer->openToFile($path);
        $build($writer);
        $writer->close();

        return response()
            ->download($path, "{$name}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
