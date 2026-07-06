<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

function budgetPlannerVersionForPermsTest(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    return BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('denies a user with no relevant role any access to the Budget Planner nav/pages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/admin/budget-planner/budget-versions')->assertForbidden();
    $this->get('/admin/budget-planner/investment-types')->assertForbidden();
    $this->get('/admin/budget-planner/budget-comparison')->assertForbidden();
});

it('denies a user with an unrelated backend role (e.g. tools_manager)', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'tools_manager');
    $this->actingAs($user);

    $this->get('/admin/budget-planner/budget-versions')->assertForbidden();
});

it('grants a budget_manager full access to every Budget Planner screen', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);

    $version = budgetPlannerVersionForPermsTest();

    $this->get('/admin/budget-planner/budget-versions')->assertOk();
    $this->get("/admin/budget-planner/budget-versions/{$version->id}/edit")->assertOk();
    $this->get('/admin/budget-planner/investment-types')->assertOk();
    $this->get('/admin/budget-planner/budget-comparison')->assertOk();
});

it('grants admin the budget list and interior, but NOT the owner-tier Investment Types', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'admin');
    $this->actingAs($user);

    $version = budgetPlannerVersionForPermsTest();

    $this->get('/admin/budget-planner/budget-versions')->assertOk();
    $this->get("/admin/budget-planner/budget-versions/{$version->id}/edit")->assertOk();
    $this->get('/admin/budget-planner/investment-types')->assertForbidden();
});

it('grants super_admin full access to the Budget Planner via the Shield bypass', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    $version = budgetPlannerVersionForPermsTest();

    $this->get('/admin/budget-planner/budget-versions')->assertOk();
    $this->get("/admin/budget-planner/budget-versions/{$version->id}/edit")->assertOk();
});

it('never grants budget_manager access to the system Activity Log (super_admin only)', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);

    $this->get('/admin/activities')->assertForbidden();
});

it('does not add budget_manager to the protected-roles list, so admin can freely grant/revoke it', function () {
    expect(\App\Filament\Resources\UserResource::PROTECTED_ROLES)->not->toContain('budget_manager');
});
