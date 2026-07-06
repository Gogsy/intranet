<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModuleActions;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApplicationPolicy
{
    use HandlesAuthorization, AuthorizesModuleActions;

    protected function moduleKey(): string
    {
        return 'apps';
    }
}
