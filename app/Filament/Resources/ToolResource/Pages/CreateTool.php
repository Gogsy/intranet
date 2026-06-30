<?php

namespace App\Filament\Resources\ToolResource\Pages;

use App\Filament\Resources\ToolResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTool extends CreateRecord
{
    protected static string $resource = ToolResource::class;

    // spremi samo filename ikone (ne cijelu putanju)
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['icon_upload'])) {
            $data['icon'] = basename($data['icon_upload']);
            unset($data['icon_upload']);
        }
        return $data;
    }
}
