<?php

namespace App\Filament\Resources\InvestmentTypeResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use App\Filament\Resources\InvestmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInvestmentTypes extends ManageRecords
{
    protected static string $resource = InvestmentTypeResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
