<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // When true (and provider = nesy), the public download asks the API
            // for the latest build on every click and redirects to it — nothing
            // is stored locally. This mirrors the standalone nesyapk script.
            $table->boolean('live_download')->default(false)->after('update_app_name');

            // The API "link" — overridable per company. Null falls back to the
            // default endpoint (NesyVersionFetcher::ENDPOINT).
            $table->string('update_endpoint')->nullable()->after('live_download');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['live_download', 'update_endpoint']);
        });
    }
};
