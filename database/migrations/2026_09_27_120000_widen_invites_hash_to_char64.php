<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanent invites are generated with bin2hex(random_bytes(32))
     * (64 hex chars) while the column was char(32) — inserts were
     * truncated/rejected.
     */
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->char('hash', 64)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->char('hash', 32)->change();
        });
    }
};
