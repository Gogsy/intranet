<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doc_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('doc_nodes')->nullOnDelete();
            $table->string('title');
            $table->string('slug');                         // slug je unikatan u okviru parenta
            $table->string('summary')->nullable();          // kratki opis (na kartici)
            $table->text('description')->nullable();        // duži opis (na showu)
            $table->string('brand_color', 7)->nullable();   // #RRGGBB (ako ikad poželiš)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);         // hijerarhija: /root/child/...
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_nodes');
    }
};
