<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use MWGuerra\FileManager\Filament\Pages\FileManager;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('gates the File Manager page behind manage_files', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'tools_manager'); // no manage_files
    $this->actingAs($user);

    expect(FileManager::canAccess())->toBeFalse();
});

it('grants the File Manager to a user with manage_files', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage_files');
    $this->actingAs($user);

    expect(FileManager::canAccess())->toBeTrue();
});

it('grants the File Manager to super_admin via the Shield bypass', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    expect(FileManager::canAccess())->toBeTrue();
});

it('grants the File Manager to the admin role', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'admin');
    $this->actingAs($user);

    expect($user->can('manage_files'))->toBeTrue()
        ->and(FileManager::canAccess())->toBeTrue();
});

it('exposes only the File Manager page, not the read-only twin or demo pages', function () {
    expect(route('filament.admin.pages.file-manager'))->toContain('/admin/file-manager');

    // The File System, Schema Example and Embed test routes are not registered.
    expect(fn () => route('filament.admin.pages.file-system'))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
});
