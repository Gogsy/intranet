<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Apps can now be served from the Versions channel, so the single
        // `link` APK is optional.
        Schema::table('applications', function (Blueprint $table) {
            $table->string('link')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('link')->nullable(false)->default('')->change();
        });
    }
};
