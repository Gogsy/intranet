<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesModuleActions;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocAttachmentPolicy
{
    use HandlesAuthorization, AuthorizesModuleActions;

    protected function moduleKey(): string
    {
        return 'docs';
    }
}
