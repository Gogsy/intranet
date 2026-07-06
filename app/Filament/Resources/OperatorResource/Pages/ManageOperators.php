<?php

namespace App\Filament\Resources\OperatorResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\OperatorResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageOperators extends ManageRecords
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
