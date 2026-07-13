<?php

namespace App\Exports\InvoiceTracker;

use App\Models\Invoice;
use App\Support\InvoiceTracker\Months;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InvoicesExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['Supplier', 'Category', 'Year', 'Month', 'Amount (EUR)', 'SAP reference', 'Note', 'Entered at'];
    }

    public function collection(): Collection
    {
        return Invoice::query()
            ->with(['supplier', 'category'])
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $invoice) => [
                $invoice->supplier->name,
                $invoice->category?->name ?? 'Uncategorized',
                $invoice->year,
                Months::name($invoice->month),
                (float) $invoice->amount,
                $invoice->sap_reference,
                $invoice->note,
                $invoice->created_at?->format('Y-m-d H:i'),
            ]);
    }
}
