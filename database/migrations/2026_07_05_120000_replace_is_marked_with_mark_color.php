<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_month_values', function (Blueprint $table) {
            // Colour of the tracking mark (green/yellow/red/blue/purple, see
            // BudgetPlannerOptions::MARK_COLORS), null = unmarked. Replaces
            // the boolean is_marked so a mark can carry a chosen colour.
            $table->string('mark_color', 20)->nullable()->after('amount');
        });

        DB::table('expense_month_values')->where('is_marked', true)->update(['mark_color' => 'green']);

        Schema::table('expense_month_values', function (Blueprint $table) {
            $table->dropColumn('is_marked');
        });
    }

    public function down(): void
    {
        Schema::table('expense_month_values', function (Blueprint $table) {
            $table->boolean('is_marked')->default(false)->after('amount');
        });

        DB::table('expense_month_values')->whereNotNull('mark_color')->update(['is_marked' => true]);

        Schema::table('expense_month_values', function (Blueprint $table) {
            $table->dropColumn('mark_color');
        });
    }
};
