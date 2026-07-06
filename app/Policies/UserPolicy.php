<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModuleActions;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Gated by view_users / manage_users. Role ASSIGNMENT is stricter and handled
 * separately in UserResource (admin may assign unprivileged roles, only
 * super_admin may grant/revoke super_admin & admin).
 */
class UserPolicy
{
    use HandlesAuthorization, AuthorizesModuleActions;

    protected function moduleKey(): string
    {
        return 'users';
    }
}
