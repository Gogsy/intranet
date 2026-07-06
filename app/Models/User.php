<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, LogsModelActivity;

    /**
     * Allow-list of role names that may enter the Filament admin panel.
     * Roles are being rebuilt step by step (see RolesAndPermissionsSeeder) —
     * names listed here that don't exist yet are harmless (hasAnyRole is
     * simply false); keep the list in sync as backend roles are (re)introduced.
     */
    public const BACKEND_ROLES = [
        'super_admin', 'admin', 'budget_expenses',
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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
}
