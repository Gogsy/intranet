<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * THE single source of truth for role names, permissions and panel access.
 * Any role name not listed in ROLES below gets deleted on every run (see the
 * Role::whereNotIn prune in run()) — a role created by hand through the
 * Shield UI (rather than added here) is stale by definition.
 *
 * `label` and `description` are deliberately NOT set here: they're plain
 * editable fields on the role form (App\Filament\Resources\RoleResource) —
 * whoever manages roles writes those directly in the UI, and this seeder
 * never overwrites them. Likewise `can_access_panel` is a per-role DB flag,
 * editable on that same form — not a hardcoded role-name allow-list — so
 * whether a role can log into /admin is visible and adjustable on the spot.
 *
 * Permissions are grouped per module — view_<module> (read-only) and
 * manage_<module> (create/edit/delete; implies view in every check), plus
 * export_phone_book for the public directory export. Enforced by
 * App\Policies\Concerns\AuthorizesModuleActions (tools, apps, docs, users)
 * and App\Concerns\AuthorizesViaModulePermission (phone book). The IT Budget
 * Planner uses its own granular permission set (see PERMISSIONS below),
 * enforced directly in its resources/pages/relation managers. Not
 * permission-gated at all, super_admin only: roles & permissions
 * (RolePolicy), the activity log and the SecurityOverview widget.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** Every permission that exists, grouped by module. */
    private const PERMISSIONS = [
        'view_tools', 'manage_tools',
        'view_apps', 'manage_apps',
        'view_docs', 'manage_docs',
        // view_phone_book also reveals hidden numbers on the public /imenik;
        // export_phone_book allows the public /imenik/export download.
        'view_phone_book', 'manage_phone_book', 'export_phone_book',
        'view_users', 'manage_users',
        // GeneralSettings + MailSettings (SMTP) pages.
        'manage_settings',
        // See & use the roles checkbox on the user form. PROTECTED_ROLES
        // (super_admin) stay grantable by super_admin only regardless.
        'assign_roles',
        // Security group: Activity Log, Authentication Log, Active Sessions
        // and the SecurityOverview dashboard widget. super_admin passes via
        // bypass; held explicitly only by the security_overview role.
        'view_security',
        // File Manager (Security group): browse/upload/rename/delete every
        // file on the 'public' disk. Powerful — reaches every module's
        // uploads — so it's a distinct permission, held by admin + super_admin.
        'manage_files',
        // Tool Stats page (Administration group): click counts/trends for
        // Web Tools. Held by NO role right now — super_admin passes via
        // bypass; assignable to other roles from the role edit screen.
        'view_tool_stats',
        // IT Budget Planner — deliberately granular:
        //   view_budget          list/enter budgets, Investments tab & widgets, comparison
        //   edit_budget_items    add/edit/delete investment rows (NOT decision; respects lock)
        //   view_budget_expenses Expenses tab, expenses widgets, expenses export (with export_budget)
        //   edit_budget_expenses edit expense rows (respects lock)
        //   export_budget        export buttons (investments; expenses only with view_budget_expenses)
        //   manage_budget        owner tier: create/delete/settings/lock/import, Investment
        //                        Types, Change log, decision & locked realization edits.
        //                        Held by NO role right now — super_admin passes via bypass.
        'view_budget', 'edit_budget_items',
        'view_budget_expenses', 'edit_budget_expenses',
        'export_budget', 'manage_budget',
    ];

    /** Starter Investment Types seeded for the Budget Planner lookup table. */
    private const INVESTMENT_TYPES = ['Hardware', 'Computer Software', 'Edukacija', 'Ostalo'];

    /**
     * Each role: whether it can log into /admin, and the exact permissions
     * it holds. `permissions => null` = super admin (Shield Gate::before
     * bypass — passes EVERY check without explicit permissions).
     */
    private const ROLES = [
        'super_admin' => [
            'can_access_panel' => true,
            'permissions' => null,
        ],
        'admin' => [
            'can_access_panel' => true,
            'permissions' => [
                'view_tools', 'manage_tools',
                'view_apps', 'manage_apps',
                'view_docs', 'manage_docs',
                'view_phone_book', 'manage_phone_book', 'export_phone_book',
                'view_users', 'manage_users',
                'manage_settings', 'assign_roles',
                'manage_files',
                'view_budget', 'edit_budget_items', 'export_budget',
            ],
        ],
        // Dopunska rola (dodaje se uz npr. Admin): kartica Expenses na budgetima.
        'budget_expenses' => [
            'can_access_panel' => true,
            'permissions' => ['view_budget', 'view_budget_expenses', 'edit_budget_expenses'],
        ],
        // Dopunska rola koju dodjeljuje isključivo Super Admin: grupa Security.
        'security_overview' => [
            'can_access_panel' => true,
            'permissions' => ['view_security'],
        ],
        // Isključivo modul Dokumentacija: pregled i uređivanje.
        'docs_manager' => [
            'can_access_panel' => true,
            'permissions' => ['view_docs', 'manage_docs'],
        ],
        // ---- Front-end-only role (Imenik) -----------------------------------
        // can_access_panel ostaje false: prijavljuju se samo na javnu web
        // stranicu (/imenik), ne u /admin.
        'phonebook_viewer' => [
            'can_access_panel' => false,
            'permissions' => ['view_phone_book'],
        ],
        'phonebook_finance' => [
            'can_access_panel' => false,
            'permissions' => ['view_phone_book', 'export_phone_book'],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Sve ostalo se briše: stare Shield per-action permisije
        // (view_any_tool, force_delete_user, …), page_/widget_ permisije i
        // svaki drugi ostatak. Pivot redovi se brišu automatski (cascade).
        Permission::whereNotIn('name', self::PERMISSIONS)->delete();

        // Briše i sve role koje nisu na kanonskoj listi — npr. rolu ručno
        // napravljenu preko Shield UI-a s imenom koje ovdje nikad nije
        // dodano. Ovaj fajl je jedini izvor istine za role, pa je sve ostalo
        // zastarjelo. Pivot redovi (model_has_roles) se brišu automatski.
        Role::whereNotIn('name', array_keys(self::ROLES))->delete();

        foreach (self::ROLES as $name => $cfg) {
            $role = Role::findOrCreate($name);
            // can_access_panel se postavlja samo kad rola tek nastaje —
            // nakon toga je to polje kojim upravlja admin kroz formu role.
            if ($role->wasRecentlyCreated) {
                $role->can_access_panel = $cfg['can_access_panel'];
                $role->save();
            }

            if ($cfg['permissions'] !== null) {
                $role->syncPermissions($cfg['permissions']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::INVESTMENT_TYPES as $index => $name) {
            \App\Models\InvestmentType::firstOrCreate(['name' => $name], ['sort_order' => $index]);
        }
    }
}
