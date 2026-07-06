<?php

use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

it('locks and unlocks a version through the Filament page actions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);

    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'PLAN',
        'name' => 'Plan 2026',
        'editable_from_month' => 1,
        'editable_to_month' => 12,
        'status' => 'DRAFT',
    ]);

    Livewire::test(EditBudgetVersion::class, ['record' => $version->getRouteKey()])
        ->callAction('lock')
        ->assertHasNoActionErrors();

    expect($version->fresh()->status)->toBe('LOCKED');

    Livewire::test(EditBudgetVersion::class, ['record' => $version->getRouteKey()])
        ->callAction('unlock', data: ['reason' => 'Need to fix a typo'])
        ->assertHasNoActionErrors();

    $fresh = $version->fresh();
    expect($fresh->status)->toBe('TEMPORARILY_UNLOCKED');
    expect($fresh->unlockEvents()->count())->toBe(1);
});
