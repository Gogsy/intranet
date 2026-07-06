<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * THE single source of truth for the whole authorization model.
 *
 * ROLES ARE BEING REBUILT STEP BY STEP: for now only super_admin exists.
 * Further roles will be (re)introduced here one at a time as they are
 * designed. When adding a backend role, also add its name to
 * User::BACKEND_ROLES so it can enter the admin panel.
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
     * Each role: a friendly label, a description, and the exact permissions
     * it holds. `permissions => null` = super admin (Shield Gate::before
     * bypass — passes EVERY check without explicit permissions).
     */
    private const ROLES = [
        'super_admin' => [
            'label' => 'Super Admin',
            'description' => 'Bez ograničenja: potpuni pristup svemu. Jedina rola koja može uređivati role i permisije, dodijeliti Super Admin rolu drugom korisniku, vidjeti activity log/security i upravljati budgetima (postavke, lock, import, brisanje, decision, change log). Prvi korisnik (kreiran kroz install wizard) je Super Admin.',
            'permissions' => null,
        ],
        'admin' => [
            'label' => 'Administrator',
            'description' => 'Upravlja svim sadržajem (Web Tools, App Downloads, Dokumentacija, Imenik), korisnicima, postavkama (uklj. SMTP) i dodjeljuje role (uklj. Admin — Super Admin rolu ne vidi). Na IT Budgetu: vidi budgete i investments, uređuje investment retke dok budget nije zaključan (osim decision), exporta investments. NE vidi: Investment Types, Expenses, Change log, postavke/kreiranje/brisanje/lock/import budgeta, activity log, role.',
            'permissions' => [
                'view_tools', 'manage_tools',
                'view_apps', 'manage_apps',
                'view_docs', 'manage_docs',
                'view_phone_book', 'manage_phone_book', 'export_phone_book',
                'view_users', 'manage_users',
                'manage_settings', 'assign_roles',
                'view_budget', 'edit_budget_items', 'export_budget',
            ],
        ],
        'budget_expenses' => [
            'label' => 'Budget — Expenses (proširenje)',
            'description' => 'Dodatak (npr. uz Admin rolu): otključava Expenses tab na budgetima — pregled i uređivanje troškova dok budget nije zaključan, expenses widgete i (uz pravo exporta) expenses export. Change log ostaje samo za Super Admina.',
            'permissions' => ['view_budget', 'view_budget_expenses', 'edit_budget_expenses'],
        ],
        // ---- Front-end-only roles (Imenik) ----------------------------------
        // NOT in User::BACKEND_ROLES, so /admin refuses them entirely — they
        // only sign in on the public website (/imenik).
        'phonebook_viewer' => [
            'label' => 'Imenik — vidi skrivene brojeve (web)',
            'description' => 'Ulogira se na web stranicu i vidi SVE brojeve u imeniku, uključujući skrivene. Ne može exportati, ne može ništa mijenjati i ne može otvoriti backend.',
            'permissions' => ['view_phone_book'],
        ],
        'phonebook_finance' => [
            'label' => 'Imenik — pregled i export (web)',
            'description' => 'Ulogira se na web stranicu, vidi SVE brojeve (uključujući skrivene) i može napraviti export cijelog imenika. Ne može ništa mijenjati i ne može otvoriti backend.',
            'permissions' => ['view_phone_book', 'export_phone_book'],
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Prune everything else: the old Shield per-action permissions
        // (view_any_tool, force_delete_user, …), page_/widget_ permissions and
        // any other leftovers. Pivot rows cascade.
        Permission::whereNotIn('name', self::PERMISSIONS)->delete();

        foreach (self::ROLES as $name => $cfg) {
            $role = Role::findOrCreate($name);
            $role->label = $cfg['label'];
            $role->description = $cfg['description'];
            $role->save();

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
