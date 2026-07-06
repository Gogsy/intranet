<?php

namespace App\Filament\Resources\NumberTypeResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\NumberTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNumberTypes extends ManageRecords
{
    protected static string $resource = NumberTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
