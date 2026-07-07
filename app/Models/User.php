<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
{
    use HasFactory, Notifiable, HasRoles, LogsModelActivity, AuthenticationLoggable;

    /**
     * Allow-list of role names that may enter the Filament admin panel.
     * Roles are being rebuilt step by step (see RolesAndPermissionsSeeder) —
     * names listed here that don't exist yet are harmless (hasAnyRole is
     * simply false); keep the list in sync as backend roles are (re)introduced.
     */
    public const BACKEND_ROLES = [
        'super_admin', 'admin', 'budget_expenses', 'security_overview',
        // Planned/legacy backend role names — harmless while they don't exist.
        'tools_manager', 'apps_manager', 'docs_manager',
        'phonebook_manager', 'user_manager', 'budget_manager',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // Set only via the super-admin-gated toggle on the User form; the
        // form dehydrates it for super admins only, so a non-super-admin's
        // submission never carries this key.
        'mfa_required',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Filament built-in MFA storage — encrypted at rest.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
            // Super-admin-set: this user MUST have an MFA method enabled.
            'mfa_required' => 'boolean',
        ];
    }

    /**
     * Filament panel access: back-end roles only. Front-end-only roles
     * (phonebook_viewer, phonebook_finance) are intentionally excluded.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(self::BACKEND_ROLES);
    }

    // ── Filament built-in MFA (authenticator app / TOTP) ─────────────────

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    /** Shown inside the authenticator app next to the code. */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    // ── Filament built-in MFA (email one-time codes) ─────────────────────

    public function hasEmailAuthentication(): bool
    {
        // Coerce: a freshly-created model that hasn't round-tripped the DB
        // default has this attribute unset (null), but the contract is strict bool.
        return (bool) $this->has_email_authentication;
    }

    public function toggleEmailAuthentication(bool $condition): void
    {
        $this->has_email_authentication = $condition;
        $this->save();
    }
}
