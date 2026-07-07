<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('number_types', function (Blueprint $table) {
            // false = whole type (e.g. Data) is hidden from the public directory
            // (its numbers are only shown to logged-in Managers/Finance/admins).
            $table->boolean('is_public')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('number_types', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
