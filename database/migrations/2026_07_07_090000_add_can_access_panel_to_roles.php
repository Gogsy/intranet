<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Roles that replace the old hardcoded User::BACKEND_ROLES allow-list —
     * backfilled here once so existing installs don't get locked out the
     * moment this column defaults everyone to false. From here on the flag
     * is a plain editable field on the role (App\Filament\Resources\
     * RoleResource) — RolesAndPermissionsSeeder only sets it on first create.
     */
    private const BACKEND_ROLE_NAMES = ['super_admin', 'admin', 'budget_expenses', 'security_overview', 'docs_manager'];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('can_access_panel')->default(false)->after('description');
        });

        DB::table('roles')->whereIn('name', self::BACKEND_ROLE_NAMES)->update(['can_access_panel' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('can_access_panel');
        });
    }
};
