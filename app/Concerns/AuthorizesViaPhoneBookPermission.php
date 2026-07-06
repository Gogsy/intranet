<?php

namespace App\Concerns;

/**
 * Gates the whole Phone Book (numbers, employees, operators, number types,
 * departments, centers) behind the grouped permission pair
 * view_phone_book / manage_phone_book, plus export_phone_book for the public
 * directory export (checked in ImenikController, not here). Applied to every
 * Phone Book Filament resource so the Shield roles screen stays simple.
 */
trait AuthorizesViaPhoneBookPermission
{
    use AuthorizesViaModulePermission;

    protected static function modulePermissionKey(): string
    {
        return 'phone_book';
    }
}
