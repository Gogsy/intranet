<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('investment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('budget_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->string('status')->default('ACTIVE'); // ACTIVE | ARCHIVED
            $table->timestamps();
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_year_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // PLAN | FC1 | FC2
            $table->string('name');
            // Self-FK added in a second pass below (table must exist first).
            $table->unsignedBigInteger('baseline_version_id')->nullable();
            // Computed once at creation from `type`, then frozen.
            $table->unsignedTinyInteger('editable_from_month');
            $table->unsignedTinyInteger('editable_to_month');
            $table->string('status')->default('DRAFT'); // DRAFT | LOCKED | TEMPORARILY_UNLOCKED | ARCHIVED
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->index('budget_year_id');
            // No unique(budget_year_id, type) — multiple versions of the same
            // type per year are allowed (e.g. what-if scenarios).
        });

        Schema::create('investment_items', function (Blueprint $table) {
            $table->id();
            // Self-FK added in a second pass below (table must exist first).
            // Lineage id: seeded to the row's own id at creation (see HasOriginLineage),
            // or copied from the template row's origin_id when created via template copy.
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->foreignId('budget_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->foreignId('entered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('investment_type_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->text('proposal_comment')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_net_price', 12, 2)->default(0);
            $table->string('classification'); // Asset | Consumable | Rent
            $table->string('link_or_description')->nullable();
            $table->string('decision_status')->default('Proposed'); // Proposed|Go|No Go|Approved|Rejected|Deferred
            $table->boolean('purchased')->default(false);
            $table->text('realization_comment')->nullable();
            $table->timestamps();

            $table->index('origin_id');
            $table->index('budget_version_id');
            $table->index('decision_status');
            $table->index('purchased');
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            // Self-FK added in a second pass below (table must exist first).
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->foreignId('budget_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('account_code')->nullable();
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->text('comment')->nullable();
            $table->string('expense_type'); // MONTHLY | ONE_TIME | ANNUAL_AVR | VOLUME
            $table->timestamps();

            $table->index('origin_id');
            $table->index('budget_version_id');
            $table->index('vendor');
            $table->index('account_code');
        });

        Schema::create('expense_month_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['expense_item_id', 'month']);
        });

        Schema::create('unlock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unlocked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index('budget_version_id');
        });

        // Second pass: self-referential FKs (table must exist before it can reference itself).
        Schema::table('budget_versions', function (Blueprint $table) {
            $table->foreign('baseline_version_id')->references('id')->on('budget_versions')->nullOnDelete();
        });

        Schema::table('investment_items', function (Blueprint $table) {
            $table->foreign('origin_id')->references('id')->on('investment_items')->nullOnDelete();
        });

        Schema::table('expense_items', function (Blueprint $table) {
            $table->foreign('origin_id')->references('id')->on('expense_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_versions', function (Blueprint $table) {
            $table->dropForeign(['baseline_version_id']);
        });
        Schema::table('investment_items', function (Blueprint $table) {
            $table->dropForeign(['origin_id']);
        });
        Schema::table('expense_items', function (Blueprint $table) {
            $table->dropForeign(['origin_id']);
        });

        Schema::dropIfExists('unlock_events');
        Schema::dropIfExists('expense_month_values');
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('investment_items');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budget_years');
        Schema::dropIfExists('investment_types');
    }
};
