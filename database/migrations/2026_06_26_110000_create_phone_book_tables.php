<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('number_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('sim_card')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('number_type_id')->nullable()->constrained()->nullOnDelete();
            // employee_id null = a "free" (unassigned) number
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            // is_public = false  → hidden from anonymous visitors (privileged roles only)
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index('employee_id');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('number_types');
        Schema::dropIfExists('operators');
    }
};
