<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W1-05: Create a dedicated table for secure email confirmation tokens.
 *
 * Replaces the legacy md5(padHash(user.secret)) confirmation token
 * which is vulnerable to forgery if the database is leaked — the
 * attacker has `secret` and can compute the confirmation token.
 *
 * Uses SecureTokenService: CSPRNG token, SHA-256 digest stored in DB,
 * atomic consumption, expiry tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_confirmation_tokens', function (Blueprint $table): void {
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
        Schema::dropIfExists('email_confirmation_tokens');
    }
};
