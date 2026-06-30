<?php

namespace App\Filament\Resources\CenterResource\Pages;

use App\Filament\Resources\CenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCenters extends ManageRecords
{
    protected static string $resource = CenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
