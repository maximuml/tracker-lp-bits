<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the OAuth social-login tables and the users.provider_id column.
     * The OauthController, OauthProvider and SocialAccount models, the
     * Filament ProviderResource and the /oauth routes were removed in
     * Sprint 33.
     */
    public function up(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('oauth_providers');

        if (Schema::hasColumn('users', 'provider_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('provider_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * No-op: the tables and column were only created by the removed OAuth
     * social-login feature. Re-adding the feature would recreate them via
     * its own migrations.
     */
    public function down(): void
    {
        // No-op — tables/column are recreated by the OAuth feature's own
        // migrations if re-added.
    }
};
