<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Notifications\UserInvitation;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** No password is entered on create — set a random one; the user sets their own via the invite. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            $data['password'] = Hash::make(Str::random(40));
        }

        // Server-side role enforcement: strip any role the current user is not
        // allowed to grant (e.g. an admin trying to grant super_admin/admin via
        // a forged request). A new record has no existing roles to preserve.
        //
        // The `roles` CheckboxList uses ->relationship(), so Filament syncs it from
        // the live form state ($this->data) in saveRelationships() AFTER creation —
        // mutating only the returned $data would NOT affect the sync. We therefore
        // write the sanitised list back onto $this->data so the sync uses it too.
        if (array_key_exists('roles', $data)) {
            $clean = UserResource::sanitizeRoles((array) $data['roles'], null);
            $data['roles'] = $clean;
            $this->data['roles'] = $clean;
        }

        return $data;
    }

    /** Email the new user a "set your password" invitation. */
    protected function afterCreate(): void
    {
        $user = $this->record;

        try {
            $token = Password::broker()->createToken($user);
            $user->notify(new UserInvitation($token));
            Notification::make()->title('Invitation email sent to ' . $user->email)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('User created, but the invite email failed')
                ->body($e->getMessage() . ' — check Mail / SMTP settings, then use "Send invite" on the user.')
                ->warning()->persistent()->send();
        }
    }
}
