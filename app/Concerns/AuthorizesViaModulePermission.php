<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Gates a whole Filament module (all of its resources and pages) behind the
 * two grouped permissions of that module:
 *
 *   view_<module>   — read-only: see the navigation, lists and records
 *   manage_<module> — create / edit / delete (implies view in every check)
 *
 * Used via thin per-module traits (currently AuthorizesViaPhoneBookPermission)
 * so resources keep a single `use` line. The Budget Planner does NOT use this
 * — it has its own granular permission set enforced in its resources directly.
 *
 * super_admin bypasses via Shield's Gate::before, so it is always allowed.
 */
trait AuthorizesViaModulePermission
{
    /** Module key, e.g. 'phone_book' → view_phone_book / manage_phone_book. */
    abstract protected static function modulePermissionKey(): string;

    protected static function userCanViewModule(): bool
    {
        $user = auth()->user();
        $key = static::modulePermissionKey();

        return $user !== null && ($user->can('view_' . $key) || $user->can('manage_' . $key));
    }

    protected static function userCanManageModule(): bool
    {
        return auth()->user()?->can('manage_' . static::modulePermissionKey()) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::userCanViewModule();
    }

    public static function canView(Model $record): bool
    {
        return static::userCanViewModule();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageModule();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManageModule();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanManageModule();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanManageModule();
    }

    /** For Filament Pages (e.g. BudgetComparison), which check canAccess() instead. */
    public static function canAccess(): bool
    {
        return static::userCanViewModule();
    }
}
