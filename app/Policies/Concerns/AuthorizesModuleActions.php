<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Policy body for modules gated by the grouped permission pair
 * view_<module> / manage_<module> (see RolesAndPermissionsSeeder):
 *
 *   viewAny / view        → view_<module> OR manage_<module>
 *   every mutating action → manage_<module>
 *
 * super_admin bypasses via Shield's Gate::before.
 */
trait AuthorizesModuleActions
{
    /** Module key, e.g. 'tools' → view_tools / manage_tools. */
    abstract protected function moduleKey(): string;

    protected function canViewModule(User $user): bool
    {
        return $user->can('view_' . $this->moduleKey()) || $user->can('manage_' . $this->moduleKey());
    }

    protected function canManageModule(User $user): bool
    {
        return $user->can('manage_' . $this->moduleKey());
    }

    public function viewAny(User $user): bool
    {
        return $this->canViewModule($user);
    }

    public function view(User $user, $record = null): bool
    {
        return $this->canViewModule($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageModule($user);
    }

    public function update(User $user, $record = null): bool
    {
        return $this->canManageModule($user);
    }

    public function delete(User $user, $record = null): bool
    {
        return $this->canManageModule($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageModule($user);
    }

    public function forceDelete(User $user, $record = null): bool
    {
        return $this->canManageModule($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canManageModule($user);
    }

    public function restore(User $user, $record = null): bool
    {
        return $this->canManageModule($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->canManageModule($user);
    }

    public function replicate(User $user, $record = null): bool
    {
        return $this->canManageModule($user);
    }

    public function reorder(User $user): bool
    {
        return $this->canManageModule($user);
    }
}
