<?php

declare(strict_types=1);

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

/*
 * Shield v4 config. The authorization model is unchanged from v3:
 * Database\Seeders\RolesAndPermissionsSeeder is THE single source of truth
 * for every permission — Shield generates NOTHING (permissions.generate and
 * policies.generate are off). The role screens therefore show only the
 * "Custom" tab, fed by the custom_permissions list below, which must stay in
 * sync with the seeder's PERMISSIONS constant.
 */
return [

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        // Only the custom-permissions tab: the resource/page/widget tabs would
        // be empty anyway since generation is disabled.
        'tabs' => [
            'pages' => false,
            'widgets' => false,
            'resources' => false,
            'custom_permissions' => true,
        ],
    ],

    'tenant_model' => null,

    'auth_provider_model' => 'App\\Models\\User',

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        // Grant super_admin EVERYTHING via a Gate::before bypass (matches the
        // assumption in App\Concerns\AuthorizesViaModulePermission). Without
        // this, super_admin would be denied custom permissions like
        // `manage_phone_book`, breaking the Phone Book on the backend.
        'define_via_gate' => true,
        'intercept_gate' => 'before',
    ],

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    // snake + '_' keeps generated permission keys identical to the seeder's
    // naming (view_tools, manage_phone_book, …) even though generation is off.
    'permissions' => [
        'separator' => '_',
        'case' => 'snake',
        'generate' => false,
    ],

    // Policies are hand-written (app/Policies) — never generated.
    'policies' => [
        'path' => app_path('Policies'),
        'merge' => false,
        'generate' => false,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    'resources' => [
        'subject' => 'model',
        'manage' => [],
        'exclude' => [],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ],
    ],

    /*
     * Mirror of RolesAndPermissionsSeeder::PERMISSIONS (key => label shown on
     * the role form). Adding a permission means adding it in BOTH places.
     */
    'custom_permissions' => [
        'view_tools' => 'View Web Tools',
        'manage_tools' => 'Manage Web Tools',
        'view_apps' => 'View App Downloads',
        'manage_apps' => 'Manage App Downloads',
        'view_docs' => 'View Documentation',
        'manage_docs' => 'Manage Documentation',
        'view_phone_book' => 'View Phone Book (incl. hidden numbers)',
        'manage_phone_book' => 'Manage Phone Book',
        'export_phone_book' => 'Export Phone Book',
        'view_users' => 'View Users',
        'manage_users' => 'Manage Users',
        'manage_settings' => 'Manage Settings (General + SMTP)',
        'assign_roles' => 'Assign Roles',
        'view_budget' => 'Budget — view',
        'edit_budget_items' => 'Budget — edit investment rows',
        'view_budget_expenses' => 'Budget — view expenses',
        'edit_budget_expenses' => 'Budget — edit expenses',
        'export_budget' => 'Budget — export',
        'manage_budget' => 'Budget — manage (owner tier)',
    ],

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    'register_role_policy' => true,

];
