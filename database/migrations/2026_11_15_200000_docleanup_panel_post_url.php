<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Do cleanup" staff-panel entry linked to legacy `/docleanup.php`,
 * which now maps to the POST-only `/docleanup` compat route — clicking
 * it as a GET returned 405. The staff panel renders rows whose url
 * starts with `post:` as an inline POST form instead of an anchor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sysoppanel')) {
            return;
        }

        DB::table('sysoppanel')
            ->where('url', '/docleanup.php')
            ->update(['url' => 'post:/web/system/cleanup']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sysoppanel')) {
            return;
        }

        DB::table('sysoppanel')
            ->where('url', 'post:/web/system/cleanup')
            ->update(['url' => '/docleanup.php']);
    }
};
