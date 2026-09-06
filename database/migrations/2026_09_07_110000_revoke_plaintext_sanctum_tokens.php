<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W1-08: Revoke all existing plaintext Sanctum tokens.
 *
 * With Sanctum 'hash' => true, tokens are stored as SHA-256 hashes.
 * Existing plaintext tokens in the database will no longer match
 * incoming requests, so they must be revoked. API clients need to
 * re-issue their tokens after this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Delete all existing tokens — they're stored in plaintext and
        // won't work with hash=true. Users must re-authenticate and
        // generate new tokens.
        DB::table('personal_access_tokens')->delete();
    }

    public function down(): void
    {
        // Cannot restore deleted tokens.
    }
};
