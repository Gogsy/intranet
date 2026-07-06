<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_month_values', function (Blueprint $table) {
            // Tracking flag ("payment for this service started/was made this
            // month"), toggled from the expenses grid. Deliberately NOT a
            // budget value — it stays editable even on locked versions.
            $table->boolean('is_marked')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('expense_month_values', function (Blueprint $table) {
            $table->dropColumn('is_marked');
        });
    }
};
