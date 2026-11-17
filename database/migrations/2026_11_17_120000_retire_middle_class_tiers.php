<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the Crazy User / Insane User / Extreme User tiers from the
 * auto-promotion ladder (kept chain: User → Power → Elite → Veteran →
 * Ultimate → Nexus Master). Users stored on a retired tier are moved to
 * the nearest lower kept tier; `max_class_once` follows the same remap.
 * The retired tiers' promotion/demotion settings are deleted so stale
 * configuration cannot re-enter the ladder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->whereIn('class', [4, 5])->update(['class' => 3]);
        DB::table('users')->where('class', 7)->update(['class' => 6]);
        DB::table('users')->whereIn('max_class_once', [4, 5])->update(['max_class_once' => 3]);
        DB::table('users')->where('max_class_once', 7)->update(['max_class_once' => 6]);

        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('name', [
            'account.cutime', 'account.cudl', 'account.cuprratio', 'account.cuderatio', 'account.4_min_seed_points',
            'account.iutime', 'account.iudl', 'account.iuprratio', 'account.iuderatio', 'account.5_min_seed_points',
            'account.exutime', 'account.exudl', 'account.exuprratio', 'account.exuderatio', 'account.7_min_seed_points',
        ])->delete();
    }

    public function down(): void
    {
        // The merged tiers cannot be told apart once remapped; the deleted
        // settings rows are not restored.
    }
};
