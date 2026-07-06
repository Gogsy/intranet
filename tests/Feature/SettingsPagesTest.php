<?php

use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\MailSettings;
use App\Models\AppSetting;
use App\Models\MailSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

function settingsAdmin(): User
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'admin');

    return $user;
}

/* -----------------------------------------------------------------
 | Access control
 | ----------------------------------------------------------------- */

it('blocks users without manage_settings from both settings pages', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'phonebook_viewer');
    $this->actingAs($user);

    $this->get('/admin/general-settings')->assertForbidden();
    $this->get('/admin/mail-settings')->assertForbidden();
});

it('allows admins to open both settings pages', function () {
    $this->actingAs(settingsAdmin());

    $this->get('/admin/general-settings')->assertOk();
    $this->get('/admin/mail-settings')->assertOk();
});

/* -----------------------------------------------------------------
 | Dashboard
 | ----------------------------------------------------------------- */

it('renders the dashboard with system info for admins', function () {
    $this->actingAs(settingsAdmin());

    $this->get('/admin')->assertOk();

    expect(App\Filament\Widgets\SystemInfoOverview::canView())->toBeTrue();
});

it('hides the system info widget from users without manage_settings', function () {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = App\Models\User::factory()->create();
    assignTestRole($user, 'phonebook_viewer');
    $this->actingAs($user);

    expect(App\Filament\Widgets\SystemInfoOverview::canView())->toBeFalse();
});

/* -----------------------------------------------------------------
 | General settings
 | ----------------------------------------------------------------- */

it('renders the general settings form pre-filled from the current settings', function () {
    AppSetting::current()->fill([
        'app_name' => 'Overseas Portal',
        'company_name' => 'Overseas d.o.o.',
        'primary_color' => '#112233',
    ])->save();
    AppSetting::forgetCurrent();

    $this->actingAs(settingsAdmin());

    Livewire::test(GeneralSettings::class)
        ->assertSchemaStateSet([
            'app_name' => 'Overseas Portal',
            'company_name' => 'Overseas d.o.o.',
            'primary_color' => '#112233',
        ]);
});

it('saves general settings and persists them', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(GeneralSettings::class)
        ->fillForm([
            'app_name' => 'Novi Portal',
            'company_name' => 'Nova Firma',
            'primary_color' => '#AA0000',
            'accent_color' => '#00BB00',
            'logo_height' => 60,
            'admin_logo_height' => 56,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    AppSetting::forgetCurrent();
    $s = AppSetting::current();

    expect($s->app_name)->toBe('Novi Portal')
        ->and($s->company_name)->toBe('Nova Firma')
        ->and($s->primary_color)->toBe('#AA0000')
        ->and($s->accent_color)->toBe('#00BB00')
        ->and($s->logo_height)->toBe(60)
        ->and($s->admin_logo_height)->toBe(56);
});

it('falls back to the default admin logo height when unset', function () {
    expect(AppSetting::current()->admin_logo_height)
        ->toBe(AppSetting::DEFAULT_ADMIN_LOGO_HEIGHT);
});

it('rejects an admin logo height outside the allowed range', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(GeneralSettings::class)
        ->fillForm(['admin_logo_height' => 5])
        ->call('save')
        ->assertHasFormErrors(['admin_logo_height']);
});

it('keeps the original filename of uploaded files', function () {
    Illuminate\Support\Facades\Storage::fake('public');
    $this->actingAs(settingsAdmin());

    Livewire::test(GeneralSettings::class)
        ->fillForm(['logo_path' => Illuminate\Http\UploadedFile::fake()->image('company-logo.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    AppSetting::forgetCurrent();
    expect(AppSetting::current()->logo_path)->toBe('branding/company-logo.png');
    Illuminate\Support\Facades\Storage::disk('public')->assertExists('branding/company-logo.png');
});

it('rejects a logo height outside the allowed range', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(GeneralSettings::class)
        ->fillForm(['logo_height' => 9999])
        ->call('save')
        ->assertHasFormErrors(['logo_height']);
});

/* -----------------------------------------------------------------
 | Mail settings
 | ----------------------------------------------------------------- */

it('saves mail settings, forcing the smtp mailer', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->fillForm([
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'mailer@example.com',
            'password' => 'secret-app-password',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Example',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Mail settings saved');

    $s = MailSetting::query()->sole();

    expect($s->mailer)->toBe('smtp')
        ->and($s->host)->toBe('smtp.example.com')
        ->and($s->port)->toBe(465)
        ->and($s->encryption)->toBe('ssl')
        ->and($s->username)->toBe('mailer@example.com')
        ->and($s->password)->toBe('secret-app-password') // decrypted by the cast
        ->and($s->from_address)->toBe('noreply@example.com')
        ->and($s->from_name)->toBe('Example');

    // save() also applies the settings to the runtime mail config
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.from.address'))->toBe('noreply@example.com');
});

it('stores encryption "none" as null', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->fillForm(['host' => 'mail.internal', 'port' => 25, 'encryption' => 'none'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(MailSetting::query()->sole()->encryption)->toBeNull();
});

it('keeps the existing password when the password field is left blank', function () {
    MailSetting::create([
        'mailer' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587,
        'password' => 'original-password',
    ]);

    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->fillForm(['host' => 'smtp.example.com', 'port' => 587, 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(MailSetting::query()->sole()->password)->toBe('original-password');
});

it('requires an smtp host to save mail settings', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->fillForm(['host' => null])
        ->call('save')
        ->assertHasFormErrors(['host']);
});

it('sends a test email via the header action', function () {
    // No host configured → MailSetting::apply() is a no-op and the test
    // mail goes through the array transport from phpunit.xml.
    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->callAction('sendTest', data: ['to' => 'target@example.com'])
        ->assertHasNoFormErrors()
        ->assertNotified('Test email sent to target@example.com');
});

it('validates the recipient of the test email', function () {
    $this->actingAs(settingsAdmin());

    Livewire::test(MailSettings::class)
        ->callAction('sendTest', data: ['to' => 'not-an-email'])
        ->assertHasFormErrors(['to' => 'email']);
});
