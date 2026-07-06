<?php

namespace App\Exports;

use App\Models\BudgetVersion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InvestmentItemsExport implements FromCollection, WithHeadings
{
    public function __construct(protected BudgetVersion $version)
    {
    }

    public function headings(): array
    {
        return [
            'Mjesec', 'Zadnje uredio', 'Vrsta investicije', 'Opis', 'Komentar / prijedlog',
            'Količina', 'Jedinična neto cijena', 'Ukupno', 'Klasifikacija', 'Link i/ili opis',
            'Status odluke', 'Kupljeno', 'Napomena realizacije',
        ];
    }

    public function collection(): Collection
    {
        return $this->version->investmentItems()
            ->with(['investmentType', 'enteredBy'])
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                $item->month,
                $item->enteredBy?->name,
                $item->investmentType?->name,
                $item->description,
                $item->proposal_comment,
                (float) $item->quantity,
                (float) $item->unit_net_price,
                $item->total,
                $item->classification,
                $item->link_or_description,
                $item->decision_status,
                $item->purchased ? 'Da' : 'Ne',
                $item->realization_comment,
            ]);
    }
}
