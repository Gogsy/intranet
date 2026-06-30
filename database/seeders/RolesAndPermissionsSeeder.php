<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission name granted to every backend role so the Site Map navigation
     * aid is reachable. SiteMap's own canAccess() already allows any panel user,
     * but we grant this page permission too so access stays correct if the page
     * is ever switched to Shield's HasPageShield gating. firstOrCreate'd in run()
     * so it works even if the page file isn't present when generate ran.
     */
    private const SITE_MAP_PERMISSION = 'page_SiteMap';

    /** Single permission that gates the entire Phone Book (numbers, employees,
     *  operators, number types, departments, centers) — see
     *  App\Concerns\AuthorizesViaPhoneBookPermission. */
    private const PHONE_BOOK_PERMISSION = 'manage_phone_book';

    /** Old per-resource Phone Book permission suffixes, now consolidated and pruned. */
    private const LEGACY_PHONE_BOOK_SUFFIXES = [
        '_phone::number', '_employee', '_department', '_center', '_operator', '_number::type',
    ];

    /**
     * Each role: a friendly label, a plain-English description of what it can do,
     * and the permission suffixes it owns. `suffixes => null` = super admin (bypass,
     * no explicit permissions). `backend => false` = front-end-only role (no /admin).
     * `extra` = exact permission names (not suffix-matched) granted on top of suffixes.
     */
    private array $roles = [
        'super_admin' => [
            'label' => 'Super Admin',
            'description' => 'Full access to everything, and the ONLY role that can manage roles & permissions and assign roles to users.',
            'suffixes' => null,
            'backend' => true,
        ],
        'admin' => [
            'label' => 'Administrator',
            'description' => 'Manages all content and users, but CANNOT manage roles/permissions or assign roles — only a Super Admin can do that.',
            'suffixes' => ['_tool', '_application', '_doc::node', '_doc::attachment', '_user'],
            // Phone Book is now one permission. SiteMap allowed. NOT
            // page_GeneralSettings / page_MailSettings (super_admin-only), NOT
            // view_activity (ActivityResource is super_admin-only via canAccess).
            'extra' => [self::SITE_MAP_PERMISSION, self::PHONE_BOOK_PERMISSION],
            'backend' => true,
        ],
        'tools_manager' => [
            'label' => 'Web Tools Manager',
            'description' => 'Can add, edit and remove Web Tools. No access to other sections or the admin beyond Web Tools.',
            'suffixes' => ['_tool'],
            'extra' => [self::SITE_MAP_PERMISSION],
            'backend' => true,
        ],
        'apps_manager' => [
            'label' => 'App Downloads Manager',
            'description' => 'Can manage App Downloads and their versions (incl. Nesy fetch). No access to other sections.',
            'suffixes' => ['_application'],
            'extra' => [self::SITE_MAP_PERMISSION],
            'backend' => true,
        ],
        'docs_manager' => [
            'label' => 'Documentation Manager',
            'description' => 'Can manage Documentation sections and documents. No access to other sections.',
            'suffixes' => ['_doc::node', '_doc::attachment'],
            'extra' => [self::SITE_MAP_PERMISSION],
            'backend' => true,
        ],
        'phonebook_manager' => [
            'label' => 'Phone Book Manager',
            'description' => 'Can manage the phone book — numbers, employees, departments, centers, operators and types.',
            'suffixes' => [],
            'extra' => [self::SITE_MAP_PERMISSION, self::PHONE_BOOK_PERMISSION],
            'backend' => true,
        ],
        'user_manager' => [
            'label' => 'User Manager',
            'description' => 'Can create and edit users. Cannot manage content, and cannot assign roles or manage permissions (Super Admin only).',
            'suffixes' => ['_user'],
            'extra' => [self::SITE_MAP_PERMISSION],
            'backend' => true,
        ],
        'manager' => [
            'label' => 'Phone Book — View All (front-end)',
            'description' => 'Signs in on the website to see ALL phone numbers, including hidden ones. Cannot edit anything and cannot open the admin.',
            'suffixes' => [],
            'backend' => false,
        ],
        'finance' => [
            'label' => 'Finance (front-end)',
            'description' => 'Signs in on the website to view and export the full list of phone numbers. Cannot edit anything and cannot open the admin.',
            'suffixes' => [],
            'backend' => false,
        ],
    ];

    public function run(): void
    {
        // Phone Book is now a single permission: prune the old per-resource
        // permissions (Shield no longer generates them) and ensure the new one exists.
        foreach (self::LEGACY_PHONE_BOOK_SUFFIXES as $suffix) {
            Permission::where('name', 'like', '%' . $suffix)->delete();
        }
        Permission::findOrCreate(self::PHONE_BOOK_PERMISSION, 'web');

        foreach ($this->roles as $name => $cfg) {
            $role = Role::findOrCreate($name);
            $role->label = $cfg['label'];
            $role->description = $cfg['description'];
            $role->save();

            if (is_array($cfg['suffixes'])) {
                $perms = collect();
                foreach ($cfg['suffixes'] as $suffix) {
                    $perms = $perms->merge(Permission::where('name', 'like', '%' . $suffix)->pluck('name'));
                }

                // Exact-name extras (e.g. page_SiteMap). firstOrCreate so the
                // assignment still works even if shield:generate hasn't run yet.
                foreach ($cfg['extra'] ?? [] as $name) {
                    Permission::findOrCreate($name, 'web');
                    $perms->push($name);
                }

                $role->syncPermissions($perms->unique()->values()->all());
            }
        }
    }
}
