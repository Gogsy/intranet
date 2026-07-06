<?php

namespace App\Filament\Resources\BudgetVersionResource\Pages;

use Filament\Support\Enums\Width;
use App\Filament\Resources\BudgetVersionResource;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Services\BudgetVersionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBudgetVersion extends CreateRecord
{
    protected static string $resource = BudgetVersionResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function handleRecordCreation(array $data): Model
    {
        // "Year" is just a number on the form — the underlying BudgetYear row
        // is found or created automatically, never managed by the user.
        $year = BudgetYear::firstOrCreate(
            ['year' => (int) $data['year']],
            ['name' => "IT Budget {$data['year']}", 'status' => 'ACTIVE'],
        );

        $template = empty($data['template_version_id']) ? null : BudgetVersion::find($data['template_version_id']);

        $version = BudgetVersionService::createFromTemplate($year, $data['type'], $template, [
            'from' => (int) $data['editable_from_month'],
            'to' => (int) $data['editable_to_month'],
        ]);

        $version->update(['name' => $data['name']]);

        return $version;
    }
}
