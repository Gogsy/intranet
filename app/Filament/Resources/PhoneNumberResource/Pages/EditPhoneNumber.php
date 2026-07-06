<?php

namespace App\Filament\Resources\PhoneNumberResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PhoneNumberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPhoneNumber extends EditRecord
{
    protected static string $resource = PhoneNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
