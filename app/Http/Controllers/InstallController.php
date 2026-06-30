<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\MailSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * First-run install wizard.
 *
 * Multi-step, plain Blade + POST forms (no Livewire). Each step persists its own data
 * immediately; the final step stamps AppSetting::setup_completed_at which permanently
 * locks the wizard (see EnsureAppInstalled middleware).
 *
 * Steps: intro -> admin -> smtp -> branding (finish).
 */
class InstallController extends Controller
{
    /** Step (a): welcome / intro. */
    public function index(): View
    {
        return view('install.intro', ['step' => 1]);
    }

    /** Step (b): create super admin — form. */
    public function admin(): View
    {
        return view('install.admin', ['step' => 2]);
    }

    /** Step (b): create super admin — handler. */
    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Ensure roles exist before assigning (first run on a fresh DB).
        if (Role::count() === 0) {
            Artisan::call('db:seed', [
                '--class' => RolesAndPermissionsSeeder::class,
                '--force' => true,
            ]);
        }

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('super_admin');

        return redirect()->route('install.smtp');
    }

    /** Step (c): SMTP settings — form. */
    public function smtp(): View
    {
        return view('install.smtp', [
            'step' => 3,
            'settings' => MailSetting::current(),
        ]);
    }

    /** Step (c): SMTP settings — handler (reuses MailSetting fields + apply()). */
    public function storeSmtp(Request $request): RedirectResponse
    {
        // SMTP is optional during install; only validate when a host is given.
        if (filled($request->input('host'))) {
            $data = $request->validate([
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'min:1', 'max:65535'],
                'encryption' => ['nullable', 'in:tls,ssl,none'],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:255'],
                'from_address' => ['nullable', 'email', 'max:255'],
                'from_name' => ['nullable', 'string', 'max:255'],
            ]);

            if (($data['encryption'] ?? null) === 'none') {
                $data['encryption'] = null;
            }
            if (blank($data['password'] ?? null)) {
                unset($data['password']); // keep existing
            }

            $s = MailSetting::current();
            $s->mailer = 'smtp';
            $s->fill($data)->save();
            $s->apply();

            // Optional test send when requested.
            if ($request->boolean('send_test') && filled($request->input('test_to'))) {
                try {
                    Mail::raw(
                        'Test email from the install wizard. If you can read this, your SMTP settings work.',
                        fn ($m) => $m->to($request->input('test_to'))->subject('SMTP test')
                    );

                    return redirect()->route('install.smtp')
                        ->with('status', 'Test email sent to ' . $request->input('test_to') . '.');
                } catch (\Throwable $e) {
                    return redirect()->route('install.smtp')
                        ->with('error', 'Test email failed: ' . $e->getMessage());
                }
            }
        }

        return redirect()->route('install.branding');
    }

    /** Step (d): branding — form. This is the final step. */
    public function branding(): View
    {
        return view('install.branding', [
            'step' => 4,
            'settings' => AppSetting::current(),
        ]);
    }

    /** Step (d): branding — handler + FINISH. */
    public function storeBranding(Request $request): RedirectResponse
    {
        // Never complete setup without an administrator, or the app locks with no
        // way into /admin (the install guard bounces /install once completed).
        if (! User::role('super_admin')->exists()) {
            return redirect()->route('install.admin')
                ->with('error', 'Create the administrator account before finishing setup.');
        }

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $s = AppSetting::current();
        $s->app_name = $data['app_name'];
        $s->company_name = $data['company_name'] ?? null;
        if (filled($data['primary_color'] ?? null)) {
            $s->primary_color = $data['primary_color'];
        }

        if ($request->hasFile('logo')) {
            $s->logo_path = $request->file('logo')->store('branding', 'public');
        }

        // Completing branding finishes setup and permanently locks the wizard.
        $s->setup_completed_at = now();
        $s->save();
        AppSetting::forgetCurrent();

        return redirect('/');
    }
}
