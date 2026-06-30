<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('doc_nodes', function (Blueprint $table) {
            $table->dropUnique('doc_nodes_parent_id_slug_unique');
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('doc_nodes', function (Blueprint $table) {
            $table->dropUnique('doc_nodes_slug_unique');
            $table->unique(['parent_id', 'slug']);
        });
    }
};
