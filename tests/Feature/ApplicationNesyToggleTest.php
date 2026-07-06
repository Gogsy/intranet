<?php

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

function appDownloadsAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'admin');

    return $user;
}

it('shows the Nesy toggle as ON when update_provider is nesy', function () {
    $this->actingAs(appDownloadsAdmin());

    $app = Application::create([
        'name' => 'Nesy',
        'update_provider' => 'nesy',
        'update_app_name' => 'Nesy-Mobile-Prod',
    ]);

    Livewire::test(EditApplication::class, ['record' => $app->getKey()])
        ->assertSchemaStateSet(['update_provider' => true]);
});

it('keeps update_provider as nesy after saving the edit form untouched', function () {
    $this->actingAs(appDownloadsAdmin());

    $app = Application::create([
        'name' => 'Nesy',
        'update_provider' => 'nesy',
        'update_app_name' => 'Nesy-Mobile-Prod',
        'live_download' => true,
    ]);

    Livewire::test(EditApplication::class, ['record' => $app->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($app->refresh()->update_provider)->toBe('nesy')
        ->and($app->live_download)->toBeTrue();
});

it('stores nesy when the toggle is switched on and null when off', function () {
    $this->actingAs(appDownloadsAdmin());

    $app = Application::create(['name' => 'Plain app']);

    Livewire::test(EditApplication::class, ['record' => $app->getKey()])
        ->fillForm(['update_provider' => true, 'update_app_name' => 'Nesy-Mobile-Prod'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($app->refresh()->update_provider)->toBe('nesy');

    Livewire::test(EditApplication::class, ['record' => $app->getKey()])
        ->fillForm(['update_provider' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($app->refresh()->update_provider)->toBeNull();
});
