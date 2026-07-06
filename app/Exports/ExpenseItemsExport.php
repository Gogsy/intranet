<?php

namespace App\Exports;

use App\Models\BudgetVersion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExpenseItemsExport implements FromCollection, WithHeadings
{
    public function __construct(protected BudgetVersion $version)
    {
    }

    public function headings(): array
    {
        return [
            'Naziv', 'Konto', 'Dobavljač', 'Opis', 'Komentar', 'Tip',
            ...array_map(fn ($month) => (string) $month, range(1, 12)),
            'Ukupno',
        ];
    }

    public function collection(): Collection
    {
        return $this->version->expenseItems()
            ->with('monthValues')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $amounts = $item->monthValues->pluck('amount', 'month');

                return [
                    $item->name,
                    $item->account_code,
                    $item->vendor,
                    $item->description,
                    $item->comment,
                    $item->expense_type,
                    ...array_map(fn ($month) => (float) ($amounts[$month] ?? 0), range(1, 12)),
                    $item->total,
                ];
            });
    }
}
