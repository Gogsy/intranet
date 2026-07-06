<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles & permissions — RolesAndPermissionsSeeder is self-contained
        // (creates every permission itself; shield:generate is not needed).
        $this->call(RolesAndPermissionsSeeder::class);

        // Only seed the default admin@example.com credential in local/dev. Production
        // (and other-company) installs create their super admin via the install wizard,
        // so we must not ship a default credential there.
        if (app()->environment('local')) {
            $admin = \App\Models\User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin',
                    'password' => bcrypt('admin'),
                    'email_verified_at' => now(),
                ]
            );

            $admin->assignRole('super_admin');
        }
    }
}
