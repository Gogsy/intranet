<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('logo_height')->nullable(); // header logo height in px
            $table->string('favicon_path')->nullable();
            $table->string('primary_color')->nullable();   // e.g. #F58220
            $table->string('accent_color')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
