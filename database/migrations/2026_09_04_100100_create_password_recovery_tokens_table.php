<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create a dedicated table for secure password recovery tokens.
 *
 * The legacy recovery flow stores the reset token as an md5 hash in
 * the Laravel cache (recover:<md5>) and derives it from editsecret +
 * email + passhash. This migration creates a proper table for
 * CSPRNG-based recovery tokens with:
 *
 * - token_digest: SHA-256 digest of the token
 * - user_id: the user requesting recovery
 * - consumed_at: when the token was used
 * - revoked: manual revocation flag
 * - expires_at: token expiry timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_recovery_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('token_digest', 64)->index();
            $table->string('ip', 45)->default('');
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('consumed_at')->nullable();
            $table->tinyInteger('revoked')->default(0);
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_recovery_tokens');
    }
};
