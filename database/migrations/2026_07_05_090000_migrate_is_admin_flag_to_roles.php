<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the legacy users.is_admin flag. It used to be a parallel door into
 * the admin panel (User::canAccessPanel honoured it besides roles); every
 * flag-holder is converted to a real super_admin role assignment and the
 * column is dropped, leaving roles as the single source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        $ids = DB::table('users')->where('is_admin', true)->pluck('id');

        if ($ids->isNotEmpty()) {
            $role = \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');

            foreach ($ids as $id) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $role->id,
                    'model_type' => \App\Models\User::class,
                    'model_id' => $id,
                ]);
            }

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }
};
