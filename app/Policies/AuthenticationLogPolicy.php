<?php

namespace App\Policies;

use App\Models\User;
use Rappasoft\LaravelAuthenticationLog\Models\AuthenticationLog;

/**
 * Gates the (vendor) Authentication Log resource to holders of view_security
 * — super_admin (via Shield's Gate::before bypass) and the security_overview
 * role. Auto-discovered by class basename (AuthenticationLog → this policy).
 * The log is read-only: mutating abilities are denied for everyone.
 */
class AuthenticationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_security');
    }

    public function view(User $user, AuthenticationLog $log): bool
    {
        return $user->can('view_security');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuthenticationLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuthenticationLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
