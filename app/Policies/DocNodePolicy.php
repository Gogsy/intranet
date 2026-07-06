<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModuleActions;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocNodePolicy
{
    use HandlesAuthorization, AuthorizesModuleActions;

    protected function moduleKey(): string
    {
        return 'docs';
    }
}
