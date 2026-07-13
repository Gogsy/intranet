<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Display-only flags: hide a whole supplier, or individual categories
        // under a supplier, from the Invoice Tracker overview/analysis screens
        // and their exports — without touching any recorded data.
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('show_in_overview')->default(true)->after('expected_monthly');
        });

        Schema::table('supplier_categories', function (Blueprint $table) {
            $table->boolean('show_in_overview')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_categories', function (Blueprint $table) {
            $table->dropColumn('show_in_overview');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('show_in_overview');
        });
    }
};
