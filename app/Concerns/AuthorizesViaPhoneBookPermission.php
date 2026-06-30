<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Collapses the whole Phone Book (numbers, employees, operators, number types,
 * departments, centers) behind a SINGLE `manage_phone_book` permission instead
 * of a separate permission set per resource. Applied to every Phone Book
 * Filament resource so the Shield roles screen stays simple.
 *
 * super_admin bypasses via Shield's Gate::before, so it is always allowed.
 */
trait AuthorizesViaPhoneBookPermission
{
    protected static function userCanManagePhoneBook(): bool
    {
        return auth()->user()?->can('manage_phone_book') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::userCanManagePhoneBook();
    }

    public static function canCreate(): bool
    {
        return static::userCanManagePhoneBook();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManagePhoneBook();
    }

    public static function canView(Model $record): bool
    {
        return static::userCanManagePhoneBook();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanManagePhoneBook();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanManagePhoneBook();
    }
}
