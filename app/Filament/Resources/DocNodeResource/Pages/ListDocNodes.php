<?php

namespace App\Filament\Resources\DocNodeResource\Pages;

use App\Filament\Resources\DocNodeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDocNodes extends ListRecords
{
    protected static string $resource = DocNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
