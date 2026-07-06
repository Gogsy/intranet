<?php

use App\Models\Employee;
use App\Models\PhoneNumber;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

function imenikRolesTestNumbers(): array
{
    $employee = Employee::create(['full_name' => 'Pero Perić']);

    $public = PhoneNumber::create([
        'number' => '+385911111111', 'employee_id' => $employee->id, 'is_public' => true,
    ]);
    $hidden = PhoneNumber::create([
        'number' => '+385922222222', 'employee_id' => $employee->id, 'is_public' => false,
    ]);

    return [$public, $hidden];
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows guests only public numbers and no export', function () {
    [$public, $hidden] = imenikRolesTestNumbers();

    $this->get('/imenik')
        ->assertOk()
        ->assertSee($public->number)
        ->assertDontSee($hidden->number);

    $this->get('/imenik/export')->assertForbidden();
});

it('lets phonebook_viewer see hidden numbers but NOT export and NOT enter the backend', function () {
    [, $hidden] = imenikRolesTestNumbers();

    $user = User::factory()->create();
    $user->assignRole('phonebook_viewer'); // the REAL seeded role
    $this->actingAs($user);

    $this->get('/imenik')->assertOk()->assertSee($hidden->number);
    $this->get('/imenik/export')->assertForbidden();
    $this->get('/admin')->assertForbidden();
});

it('lets phonebook_finance see hidden numbers AND export, but NOT enter the backend', function () {
    [, $hidden] = imenikRolesTestNumbers();

    $user = User::factory()->create();
    $user->assignRole('phonebook_finance'); // the REAL seeded role
    $this->actingAs($user);

    $this->get('/imenik')->assertOk()->assertSee($hidden->number);

    $export = $this->get('/imenik/export');
    $export->assertOk();
    expect($export->streamedContent())->toContain($hidden->number);

    $this->get('/admin')->assertForbidden();
});
