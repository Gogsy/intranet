<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $includesVat = (bool) ($data['includes_vat'] ?? true);
        unset($data['includes_vat']);

        if (! $includesVat) {
            $data['amount'] = round((float) $data['amount'] * (1 + Invoice::VAT_RATE), 2);
        }

        return $data;
    }
}
