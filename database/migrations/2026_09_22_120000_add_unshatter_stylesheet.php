<?php

use App\Support\Cache\LegacyRedisCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Unshatter stylesheet pack (Linkin Park "From Zero" palette:
 * black/white/yellow). It is registered alongside Classic — the pack
 * carries its own light/dark variants keyed off users.theme, so no
 * theme or defstylesheet changes are required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('stylesheets')->where('uri', 'styles/Unshatter/')->exists()) {
            return;
        }

        DB::table('stylesheets')->insert([
            'uri' => 'styles/Unshatter/',
            'name' => 'Unshatter',
            'addicode' => '',
            'designer' => 'Devin',
            'comment' => 'Linkin Park From Zero / Unshatter palette — black, white, yellow. Sharp corners, own light and dark variants.',
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
        // Accounts already on Unshatter fall back to Classic via
        // Style::cssRow() once the row is gone — no user table rewrite.
        DB::table('stylesheets')->where('uri', 'styles/Unshatter/')->delete();

        try {
            app(LegacyRedisCache::class)->delete_value('stylesheet_content');
        } catch (Throwable) {
            // non-critical: the entry expires on its own
        }
    }
};
