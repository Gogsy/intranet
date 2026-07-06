<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Roles & permissions are managed EXCLUSIVELY by super_admin. Kept explicit
 * (rather than relying only on Shield's Gate::before bypass) so the intent is
 * readable and the Shield role screens stay closed to everyone else.
 */
class RolePolicy
{
    use HandlesAuthorization;

    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restore(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function replicate(User $user, Role $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function reorder(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }
}
