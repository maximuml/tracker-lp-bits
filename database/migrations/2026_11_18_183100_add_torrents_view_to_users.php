<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user torrent listing display mode ('list'|'grid') for the
 * poster-grid rework of the torrents page. Stored on users (source of
 * truth) and user_preferences (phase-1 dual-write target), following
 * the `theme` column precedent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'torrents_view')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('torrents_view', 8)->default('list')->after('theme');
            });
        }
        if (! Schema::hasColumn('user_preferences', 'torrents_view')) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->string('torrents_view', 8)->default('list')->after('theme');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('torrents_view');
        });
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('torrents_view');
        });
    }
};
