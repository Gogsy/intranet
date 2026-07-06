<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('lets a budget_manager open the Budget Planner pages', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');

    $this->actingAs($user);

    $this->get('/admin/budget-planner/budget-versions')->assertOk();
    $this->get('/admin/budget-planner/budget-versions/create')->assertOk();
    $this->get('/admin/budget-planner/investment-types')->assertOk();
});

it('blocks a user without the permission from the Budget Planner pages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/admin/budget-planner/budget-versions')->assertForbidden();
});
