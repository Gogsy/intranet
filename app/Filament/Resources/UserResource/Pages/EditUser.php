<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Server-side role enforcement on update: re-check the submitted roles
     * against the current user's privileges so a forged POST cannot grant
     * super_admin/admin (including self-elevation), and a non-super-admin
     * cannot strip protected roles the record already holds.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // NOTE: the `roles` CheckboxList uses ->relationship(), so Filament syncs it
        // from the live form state ($this->data) in saveRelationships() — not from the
        // returned $data. Write the sanitised list back onto $this->data so a forged
        // Livewire payload cannot grant super_admin/admin or self-elevate.
        if (array_key_exists('roles', $data)) {
            /** @var User $record */
            $record = $this->getRecord();
            $clean = UserResource::sanitizeRoles((array) $data['roles'], $record);
            $data['roles'] = $clean;
            $this->data['roles'] = $clean;
        }

        return $data;
    }
}
