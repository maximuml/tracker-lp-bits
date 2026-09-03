<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add secure token columns to the invites table for CSPRNG-based tokens.
 *
 * The legacy invites table stores the plaintext hash directly in the
 * `hash` column (32-char md5). This migration adds columns for the new
 * secure token protocol:
 *
 * - token_digest: SHA-256 digest of the CSPRNG token (64-char hex)
 * - consumed_at: timestamp when the token was consumed
 * - revoked: boolean flag for manual revocation
 * - expires_at: already exists from a previous migration, but we add
 *   it here if it doesn't exist (idempotent)
 *
 * The legacy `hash` column is preserved for backward compatibility
 * with existing invite links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->string('token_digest', 64)->nullable()->index()->after('hash');
            $table->dateTime('consumed_at')->nullable()->after('time_invited');
            $table->tinyInteger('revoked')->default(0)->after('valid');
        });

        // expires_at may already exist from 2022_12_10_034926
        if (! Schema::hasColumn('invites', 'expires_at')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->dateTime('expires_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex(['token_digest']);
            $table->dropColumn(['token_digest', 'consumed_at', 'revoked']);
        });

        // Do not drop expires_at — it may have been added by a prior migration
    }
};
