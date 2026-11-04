<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sysoppanel', 'adminpanel', 'modpanel'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)
                ->where('url', 'not like', '/%')
                ->where('url', 'not like', 'http%')
                ->where('url', 'not like', '#%')
                ->where('url', '!=', '')
                ->update(['url' => DB::raw("CONCAT('/', url)")]);
        }
    }

    public function down(): void
    {
        // Un-prefixing panel URLs would only recreate the broken state.
    }
};
