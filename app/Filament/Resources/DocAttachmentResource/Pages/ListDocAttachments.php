<?php

namespace App\Filament\Resources\DocAttachmentResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DocAttachmentResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListDocAttachments extends ListRecords
{
    protected static string $resource = DocAttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
