<?php

namespace App\Console\Commands;

use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin';

    protected $description = 'Creates a new admin user via CLI';

    public function handle()
    {
        $name = $this->ask('Enter the user\'s full name');
        $email = $this->ask('Enter the user\'s email');
        $password = $this->secret('Enter a password');

        if (User::where('email', $email)->exists()) {
            $this->error('⚠️ A user with that email already exists.');
            return;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // Panel access is role-based; super_admin is the full-access role.
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $this->info("✅ Admin user '{$user->email}' created successfully.");
    }
}
