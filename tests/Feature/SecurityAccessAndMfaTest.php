<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ── Security group access (view_security) ────────────────────────────────

it('denies the Security group to a plain backend user', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'tools_manager');
    $this->actingAs($user);

    $this->get('/admin/activities')->assertForbidden();
    $this->get('/admin/authentication-logs')->assertForbidden();
    $this->get('/admin/user-sessions')->assertForbidden();
});

it('grants the whole Security group to a security_overview user', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'security_overview');
    $this->actingAs($user);

    $this->get('/admin/activities')->assertOk();
    $this->get('/admin/authentication-logs')->assertOk();
    $this->get('/admin/user-sessions')->assertOk();
});

it('grants the Security group to super_admin via the Shield bypass', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    $this->get('/admin/activities')->assertOk();
    $this->get('/admin/authentication-logs')->assertOk();
    $this->get('/admin/user-sessions')->assertOk();
});

it('does not let a security_overview user reach unrelated modules', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'security_overview');
    $this->actingAs($user);

    // Read access to Security only — no user administration, no budgets.
    $this->get('/admin/budget-planner/budget-versions')->assertForbidden();
});

// ── security_overview is a protected (super-admin-only) role ──────────────

it('keeps security_overview grantable by super_admin only', function () {
    expect(\App\Filament\Resources\UserResource::PROTECTED_ROLES)->toContain('security_overview');

    $securityRoleId = \Spatie\Permission\Models\Role::where('name', 'security_overview')->value('id');
    $adminRoleId = \Spatie\Permission\Models\Role::where('name', 'admin')->value('id');

    // Acting as a non-super-admin (admin) the role is stripped from a submission.
    $admin = User::factory()->create();
    assignTestRole($admin, 'admin');
    $this->actingAs($admin);

    $clean = \App\Filament\Resources\UserResource::sanitizeRoles([$securityRoleId, $adminRoleId]);
    expect($clean)->toBe([$adminRoleId]);
});

// ── Per-user MFA enforcement ──────────────────────────────────────────────

it('lets an un-flagged user reach the panel without MFA', function () {
    $user = User::factory()->create(['mfa_required' => false]);
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    $this->get('/admin/user-sessions')->assertOk();
});

it('redirects a flagged user with no MFA set up to the MFA setup page', function () {
    $user = User::factory()->create(['mfa_required' => true]);
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    $this->get('/admin/user-sessions')
        ->assertRedirect('/admin/multi-factor-authentication/set-up');
});
