<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Exports\InvoiceTracker\InvoicesExport;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new InvoicesExport(), 'invoices.xlsx')),

            CreateAction::make(),
        ];
    }
}
