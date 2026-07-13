<?php

use App\Exceptions\BudgetVersionLockedException;
use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\BudgetVersionResource\Widgets\BudgetVersionExpensesChart;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentItem;
use App\Models\InvestmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

function budgetAdminRoleTestVersion(string $status = 'DRAFT'): BudgetVersion
{
    $year = BudgetYear::firstOrCreate(['year' => 2026], ['name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    return BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026 ' . uniqid(),
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => $status,
    ]);
}

function budgetAdminRoleTestItem(BudgetVersion $version): InvestmentItem
{
    return InvestmentItem::withoutLockGuard(fn () => $version->investmentItems()->create([
        'month' => 3,
        'investment_type_id' => InvestmentType::firstOrCreate(['name' => 'Hardware'], ['sort_order' => 0])->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_net_price' => 1000,
        'classification' => 'Asset',
        'decision_status' => 'Proposed',
    ]));
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin'); // the REAL seeded role, not a test stand-in
});

it('lets admin into the budget list and interior, but not Investment Types or the create page', function () {
    $this->actingAs($this->admin);
    $version = budgetAdminRoleTestVersion();

    $this->get('/admin/budget-planner/budget-versions')->assertOk();
    $this->get("/admin/budget-planner/budget-versions/{$version->id}/edit")->assertOk();
    $this->get('/admin/budget-planner/investment-types')->assertForbidden();
    $this->get('/admin/budget-planner/budget-versions/create')->assertForbidden();
});

it('hides the Expenses and Change log tabs from admin, but shows Expenses to budget_expenses', function () {
    $this->actingAs($this->admin);
    $version = budgetAdminRoleTestVersion();

    expect(ExpensesRelationManager::canViewForRecord($version, EditBudgetVersion::class))->toBeFalse()
        ->and(ActivitiesRelationManager::canViewForRecord($version, EditBudgetVersion::class))->toBeFalse()
        ->and(BudgetVersionExpensesChart::canView())->toBeFalse();

    $expenses = User::factory()->create();
    $expenses->assignRole(['admin', 'budget_expenses']);
    $this->actingAs($expenses);

    expect(ExpensesRelationManager::canViewForRecord($version, EditBudgetVersion::class))->toBeTrue()
        ->and(BudgetVersionExpensesChart::canView())->toBeTrue()
        // Change log stays owner-tier even with the expenses add-on.
        ->and(ActivitiesRelationManager::canViewForRecord($version, EditBudgetVersion::class))->toBeFalse();
});

it('lets admin edit investment rows while unlocked, but never the decision status', function () {
    $this->actingAs($this->admin);
    $version = budgetAdminRoleTestVersion();
    $item = budgetAdminRoleTestItem($version);

    $item->update(['description' => 'Laptop 14"']); // budget field, unlocked → OK
    expect($item->fresh()->description)->toBe('Laptop 14"');

    expect(fn () => $item->update(['decision_status' => 'Approved']))
        ->toThrow(BudgetVersionLockedException::class);
});

it('freezes EVERYTHING for admin on a locked budget, including realization fields', function () {
    $this->actingAs($this->admin);
    $version = budgetAdminRoleTestVersion('LOCKED');
    $item = budgetAdminRoleTestItem($version);

    expect(fn () => $item->update(['description' => 'Changed']))
        ->toThrow(BudgetVersionLockedException::class);
    expect(fn () => $item->update(['purchased' => true]))
        ->toThrow(BudgetVersionLockedException::class);
});

it('still lets super_admin change decisions and locked realization fields', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $version = budgetAdminRoleTestVersion('LOCKED');
    $item = budgetAdminRoleTestItem($version);

    $item->update(['decision_status' => 'Approved', 'purchased' => true]);

    expect($item->fresh()->decision_status)->toBe('Approved')
        ->and($item->fresh()->purchased)->toBeTrue();
});

it('grants admin the settings pages but never activities or Shield roles', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/general-settings')->assertOk();
    $this->get('/admin/mail-settings')->assertOk();
    $this->get('/admin/activities')->assertForbidden();
    $this->get('/admin/shield/roles')->assertForbidden();
});

it('lets admin assign the admin role but keeps super_admin, security_overview and invoice_tracker protected', function () {
    // security_overview and invoice_tracker joined super_admin as super-admin-only roles.
    expect(\App\Filament\Resources\UserResource::PROTECTED_ROLES)->toBe(['super_admin', 'security_overview', 'invoice_tracker']);

    $this->actingAs($this->admin);
    expect(\App\Filament\Resources\UserResource::canManageRoles())->toBeTrue()
        ->and(\App\Filament\Resources\UserResource::canManageProtectedRoles())->toBeFalse();

    // A forged submission trying to grant any protected role gets stripped server-side.
    $superAdminRoleId = \Spatie\Permission\Models\Role::where('name', 'super_admin')->value('id');
    $securityRoleId = \Spatie\Permission\Models\Role::where('name', 'security_overview')->value('id');
    $invoiceTrackerRoleId = \Spatie\Permission\Models\Role::where('name', 'invoice_tracker')->value('id');
    $adminRoleId = \Spatie\Permission\Models\Role::where('name', 'admin')->value('id');

    $clean = \App\Filament\Resources\UserResource::sanitizeRoles([$superAdminRoleId, $securityRoleId, $invoiceTrackerRoleId, $adminRoleId]);
    expect($clean)->toBe([$adminRoleId]);
});

it('blocks expense edits for admin without the budget_expenses add-on, allows them with it', function () {
    $version = budgetAdminRoleTestVersion();
    $expense = App\Models\ExpenseItem::withoutLockGuard(
        fn () => $version->expenseItems()->create(['name' => 'Licenses', 'expense_type' => 'MONTHLY'])
    );

    $this->actingAs($this->admin);
    expect(fn () => $expense->monthValues()->create(['month' => 1, 'amount' => 100]))
        ->toThrow(BudgetVersionLockedException::class);

    $this->admin->assignRole('budget_expenses');
    $this->admin->unsetRelation('roles')->unsetRelation('permissions');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $expense->monthValues()->create(['month' => 1, 'amount' => 100]);
    expect((float) $expense->fresh()->monthValues()->where('month', 1)->value('amount'))->toBe(100.0);
});
