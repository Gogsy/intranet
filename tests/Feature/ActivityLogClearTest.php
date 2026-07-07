<?php

use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function seedActivityEntries(): void
{
    // Two aged entries and one fresh one.
    activity()->withProperties([])->log('old one');
    activity()->withProperties([])->log('old two');
    Activity::query()->update(['created_at' => now()->subDays(45)]);
    activity()->withProperties([])->log('fresh');
}

it('lets super_admin clear the entire activity log', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    seedActivityEntries();
    expect(Activity::count())->toBeGreaterThan(0);

    Livewire::test(ListActivities::class)
        ->callAction('clearLog', data: ['scope' => 'all'])
        ->assertHasNoActionErrors();

    expect(Activity::count())->toBe(0);
});

it('clears only entries older than the selected age, keeping recent ones', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);

    seedActivityEntries();

    Livewire::test(ListActivities::class)
        ->callAction('clearLog', data: ['scope' => '30'])
        ->assertHasNoActionErrors();

    // Only the fresh entry survives (plus the one logged for the clear action itself is not recorded).
    expect(Activity::count())->toBe(1);
});

it('hides the Clear log action from a security_overview user', function () {
    $user = User::factory()->create();
    assignTestRole($user, 'security_overview');
    $this->actingAs($user);

    Livewire::test(ListActivities::class)
        ->assertActionHidden('clearLog');
});
