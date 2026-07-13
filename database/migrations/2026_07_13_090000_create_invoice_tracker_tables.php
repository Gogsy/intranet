<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
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
            $table->timestamps();

            $table->index(['year', 'month']);
            $table->unique(['supplier_id', 'category_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_budgets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('supplier_categories');
        Schema::dropIfExists('suppliers');
    }
};
