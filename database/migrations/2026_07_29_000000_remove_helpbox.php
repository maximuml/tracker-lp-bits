<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any leftover helpbox messages and their reactions.
        if (Schema::hasTable('shoutbox')) {
            $hbIds = DB::table('shoutbox')->where('type', 'hb')->pluck('id')->all();
            if (! empty($hbIds)) {
                if (Schema::hasTable('shoutbox_reactions')) {
                    DB::table('shoutbox_reactions')->whereIn('shoutbox_id', $hbIds)->delete();
                }
                DB::table('shoutbox')->where('type', 'hb')->delete();
            }
        }

        // Remove the per-user helpbox hide preference.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'hidehb')) {
            Schema::table('users', function ($table) {
                $table->dropColumn('hidehb');
            });
        }
    }

    public function down(): void
    {
        // Re-creating the column or restoring deleted helpbox rows is not practical.
    }
};
