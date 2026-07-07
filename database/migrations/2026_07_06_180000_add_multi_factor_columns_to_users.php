<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns for Filament's built-in multi-factor authentication:
 * - app_authentication_secret: encrypted TOTP secret (authenticator app)
 * - app_authentication_recovery_codes: encrypted JSON array of recovery codes
 * - has_email_authentication: whether email-code MFA is enabled
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->boolean('has_email_authentication')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'app_authentication_secret',
                'app_authentication_recovery_codes',
                'has_email_authentication',
            ]);
        });
    }
};
