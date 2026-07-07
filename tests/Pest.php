<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The production seeder only creates super_admin (roles are being rebuilt
 * step by step), so tests that need a scoped role create it themselves with
 * exactly the module permissions that role represents. Permissions must
 * already exist — seed RolesAndPermissionsSeeder first.
 */
function assignTestRole(App\Models\User $user, string $role): void
{
    $permissions = match ($role) {
        'super_admin' => null, // Shield Gate::before bypass — no explicit permissions
        // Mirrors the real admin role in RolesAndPermissionsSeeder.
        'admin' => [
            'view_tools', 'manage_tools', 'view_apps', 'manage_apps',
            'view_docs', 'manage_docs',
            'view_phone_book', 'manage_phone_book', 'export_phone_book',
            'view_users', 'manage_users',
            'manage_settings', 'assign_roles', 'manage_files',
            'view_budget', 'edit_budget_items', 'export_budget',
        ],
        // Mirrors the real budget_expenses add-on role.
        'budget_expenses' => ['view_budget', 'view_budget_expenses', 'edit_budget_expenses'],
        // Mirror the real front-end-only Imenik roles.
        'phonebook_viewer' => ['view_phone_book'],
        'phonebook_finance' => ['view_phone_book', 'export_phone_book'],
        'tools_manager' => ['view_tools', 'manage_tools'],
        'apps_manager' => ['view_apps', 'manage_apps'],
        'docs_manager' => ['view_docs', 'manage_docs'],
        'phonebook_manager' => ['view_phone_book', 'manage_phone_book', 'export_phone_book'],
        // Test-only "full budget" role: every budget permission incl. the
        // owner tier, so lock/import/changelog tests exercise those paths
        // without being super_admin.
        'budget_manager' => [
            'view_budget', 'edit_budget_items',
            'view_budget_expenses', 'edit_budget_expenses',
            'export_budget', 'manage_budget',
        ],
        'user_manager' => ['view_users', 'manage_users'],
        // Security add-on: read access to the Security group only.
        'security_overview' => ['view_security'],
    };

    $roleModel = Spatie\Permission\Models\Role::findOrCreate($role);

    if ($permissions !== null) {
        // findOrCreate each permission so tests that don't run the seeder work too.
        foreach ($permissions as $permission) {
            Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }

        $roleModel->syncPermissions($permissions);
    }

    $user->assignRole($role);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}
