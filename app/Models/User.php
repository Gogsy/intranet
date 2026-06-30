<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, \App\Concerns\LogsModelActivity;

    /** Roles that are allowed into the Filament admin panel (back-end). */
    public const BACKEND_ROLES = [
        'super_admin', 'admin', 'tools_manager', 'apps_manager', 'docs_manager',
        'phonebook_manager', 'user_manager',
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
        'is_admin', // 👈 ne zaboravi ovo ako koristiš admin flag
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
            'is_admin' => 'boolean', // 👈 dodatno osiguraj tip
        ];
    }

    /**
     * Filament panel access: back-end roles (or the legacy is_admin flag during
     * migration). Front-end-only roles (manager, finance) are intentionally excluded.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return $this->is_admin === true || $this->hasAnyRole(self::BACKEND_ROLES);
    }
}
