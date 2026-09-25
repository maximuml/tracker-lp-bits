<?php

use App\Support\Cache\LegacyRedisCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Thousand Suns stylesheet pack (cold slate + indigo/cyan
 * accent, flat minimal geometry). It is registered alongside Classic and
 * Unshatter — the pack carries its own light/dark variants keyed off
 * users.theme, so no theme or defstylesheet changes are required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('stylesheets')->where('uri', 'styles/ThousandSuns/')->exists()) {
            return;
        }

        DB::table('stylesheets')->insert([
            'uri' => 'styles/ThousandSuns/',
            'name' => 'Thousand Suns',
            'addicode' => '',
            'designer' => 'Devin',
            'comment' => 'Linkin Park A Thousand Suns palette — cold slate, indigo to cyan accent. Flat minimal look, own light and dark variants.',
        ]);

        // Style::cssRow() serves the stylesheets table from a ~26h
        // 'stylesheet_content' cache — drop it so the new pack is visible
        // immediately. Redis may legitimately be down during a migration.
        try {
            app(LegacyRedisCache::class)->delete_value('stylesheet_content');
        } catch (Throwable) {
            // non-critical: the entry expires on its own
        }
    }

    public function down(): void
    {
        // Accounts already on Thousand Suns fall back to Classic via
        // Style::cssRow() once the row is gone — no user table rewrite.
        DB::table('stylesheets')->where('uri', 'styles/ThousandSuns/')->delete();
    }
};
