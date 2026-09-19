<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 2.2: the five legacy site themes are replaced by a single
 * token-driven light/dark pair (ADR 0019). A `theme` column
 * ('auto'|'light'|'dark') is added to users (source of truth) and
 * user_preferences (phase-1 dual-write target); every existing account
 * starts on 'auto' regardless of its old stylesheet id.
 *
 * The removed theme directories make non-Classic stylesheet rows dead
 * weight: they are deleted so `Style::cssRow()` falls back to Classic,
 * and `main.defstylesheet` is repointed at the Classic row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'theme')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('theme', 10)->default('auto')->after('stylesheet');
            });
        }
        if (! Schema::hasColumn('user_preferences', 'theme')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->string('theme', 10)->default('auto')->after('stylesheet');
            });
        }

        $classic = DB::table('stylesheets')->where('uri', 'styles/Classic/')->value('id');
        if ($classic !== null) {
            DB::table('stylesheets')->where('id', '!=', $classic)->delete();
            DB::table('settings')->where('name', 'main.defstylesheet')->update(['value' => (string) $classic]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
