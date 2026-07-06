<?php

namespace App\Filament\Resources\DocNodeResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DocNodeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDocNodes extends ListRecords
{
    protected static string $resource = DocNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
