<?php

namespace App\Filament\Resources\PhoneNumberResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\PhoneNumberResource;
use App\Models\PhoneNumber;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPhoneNumbers extends ListRecords
{
    protected static string $resource = PhoneNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /** Separate assigned vs free numbers into tabs. */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(PhoneNumber::count()),
            'assigned' => Tab::make('Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('employee_id'))
                ->badge(PhoneNumber::whereNotNull('employee_id')->count()),
            'free' => Tab::make('Free (unassigned)')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('employee_id'))
                ->badge(PhoneNumber::whereNull('employee_id')->count())
                ->badgeColor('warning'),
        ];
    }
}
