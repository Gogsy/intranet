<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('version_number');       // e.g. "260"
            $table->string('file_path');            // apk on public disk
            $table->string('source')->default('manual'); // manual | api
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->text('original_url')->nullable(); // source download url (no SAS token)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'is_active']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('update_provider')->nullable()->after('sort_order'); // null | nesy
            $table->string('update_app_name')->nullable()->after('update_provider'); // e.g. Nesy-Mobile-Prod
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_versions');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['update_provider', 'update_app_name']);
        });
    }
};
