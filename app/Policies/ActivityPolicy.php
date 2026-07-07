<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * The system Activity Log is gated to view_security holders (super_admin +
 * security_overview), matching ActivityResource::canAccess. The log is
 * read-only for everyone: even a super_admin passes the mutating abilities
 * only via Shield's Gate::before bypass, never through this policy.
 */
class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_security');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('view_security');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Activity $activity): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
