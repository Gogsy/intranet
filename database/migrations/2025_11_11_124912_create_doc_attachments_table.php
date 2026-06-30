<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doc_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_node_id')->constrained('doc_nodes')->cascadeOnDelete();
            $table->string('label');                         // naziv gumba
            $table->enum('type', ['file','url'])->default('file');
            $table->string('file_path')->nullable();         // storage path (public disk)
            $table->string('url')->nullable();               // vanjski link
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doc_node_id','sort_order','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_attachments');
    }
};
