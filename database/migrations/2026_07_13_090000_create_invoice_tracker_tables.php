<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            // Unique: the Budget Planner sync resolves suppliers with a
            // find-or-create by name (from ExpenseItem.vendor free text).
            $table->string('name')->unique();
            $table->string('oib', 11)->nullable()->unique();
            $table->string('iban', 34)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('expected_monthly')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['supplier_id', 'name']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('supplier_categories')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2);
            $table->string('sap_reference')->nullable();
            $table->text('note')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index(['supplier_id', 'year']);
        });

        Schema::create('supplier_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('supplier_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            $table->string('source')->default('manual'); // manual | budget_planner
            // DB-level cascade matters: deleting a BudgetVersion cascades to its
            // expense_items purely in the DB (no Eloquent events), so the synced
            // tracker rows must be cleaned up by FK cascade too.
            $table->foreignId('expense_item_id')->nullable()->constrained('expense_items')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index(['supplier_id', 'category_id', 'year', 'month']);
            // One synced row per expense-item month; NULLs (manual rows) are
            // not constrained — their uniqueness stays form-enforced.
            $table->unique(['expense_item_id', 'year', 'month']);
        });

        // Explicit link from the Budget Planner side (vendor text -> Supplier).
        Schema::table('expense_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('vendor')
                ->constrained('suppliers')->nullOnDelete();
        });

        // Per-year pointer to the version whose expenses mirror into the
        // Invoice Tracker (the "operative" version).
        Schema::table('budget_years', function (Blueprint $table) {
            $table->foreignId('tracker_source_version_id')->nullable()
                ->constrained('budget_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_years', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracker_source_version_id');
        });
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('supplier_budgets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('supplier_categories');
        Schema::dropIfExists('suppliers');
    }
};
