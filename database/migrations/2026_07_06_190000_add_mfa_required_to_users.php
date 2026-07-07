<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user "must use MFA" flag, set by a super admin on the user form.
 * Enforced by App\Http\Middleware\EnsureMfaForFlaggedUsers: a flagged user
 * who has no MFA method enabled is redirected to the MFA setup page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('mfa_required')->default(false)->after('has_email_authentication');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mfa_required');
        });
    }
};
