<?php

namespace App\Observers;

use App\Models\User;
use Filament\Notifications\Notification;
use Spatie\Permission\Models\Role;

class RoleObserver
{
    /**
     * Front-end-only roles (see RolesAndPermissionsSeeder) — intentionally
     * absent from User::BACKEND_ROLES, so they must not trigger the warning.
     */
    private const KNOWN_FRONTEND_ROLES = ['phonebook_viewer', 'phonebook_finance'];

    /**
     * Warn on the spot (Shield's role create/edit screen) when a role's name
     * won't grant /admin access to anyone assigned only that role — the
     * mistake that silently turns into "invalid credentials" at login.
     */
    public function saved(Role $role): void
    {
        if (app()->runningInConsole()) {
            // Seeder / artisan / tinker — not an interactive Shield UI save.
            return;
        }

        if (in_array($role->name, User::BACKEND_ROLES, true) || in_array($role->name, self::KNOWN_FRONTEND_ROLES, true)) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Rola nema pristup admin panelu')
            ->body("Ime role \"{$role->name}\" nije na listi User::BACKEND_ROLES. Korisnici kojima je dodijeljena SAMO ova rola neće moći da se uloguju u /admin — dobiće grešku kao za pogrešnu lozinku. Dodaj ime role u User::BACKEND_ROLES (app/Models/User.php) ako treba pristup panelu, ili je ostavi kao front-end/dopunsku rolu.")
            ->persistent()
            ->send();
    }
}
