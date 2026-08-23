<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the five tables created by Laravel Passport. The package was
     * removed in Phase 4.3 and replaced with Sanctum as the sole token auth.
     */
    public function up(): void
    {
        Schema::dropIfExists('oauth_personal_access_clients');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
    }

    /**
     * Reverse the migrations.
     *
     * No-op: the tables were only created by Laravel Passport, which has
     * been removed from the project. Reinstalling Passport would recreate
     * them via its own migrations.
     */
    public function down(): void
    {
        // No-op — tables are recreated by Passport's own migrations if re-added
    }
};
