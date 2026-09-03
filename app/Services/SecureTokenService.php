<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Token;
use Illuminate\Support\Facades\DB;

/**
 * Secure token generation and verification for invite and recovery flows.
 *
 * Replaces the legacy token derivation that used md5(mt_rand() + passhash)
 * and md5(editsecret + email + passhash) with:
 *
 * - bin2hex(random_bytes(32)) for token generation (CSPRNG, 64-char hex)
 * - SHA-256 digest stored in DB (plaintext token never persisted)
 * - Atomic consumption in a transaction
 * - Expiry + consumed_at + revoke tracking
 *
 * Legacy tokens (md5-based, 32-char hex) are still accepted during the
 * compatibility window via {@see verifyLegacy()}.
 */
final class SecureTokenService
{
    /** Token length in bytes (before hex encoding → 64 chars). */
    public const TOKEN_BYTES = 32;

    /** Default expiry in seconds (7 days). */
    public const DEFAULT_EXPIRY = 604800;

    /**
     * Generate a cryptographically secure token.
     *
     * Returns the plaintext token (64-char hex) — store only its SHA-256
     * digest in the database. The plaintext is sent to the user via
     * email/link and never persisted.
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * Compute the SHA-256 digest of a token for storage.
     */
    public function digest(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Atomically consume a token in a transaction.
     *
     * Marks the token as consumed (sets consumed_at) only if it is still
     * valid (not expired, not already consumed, not revoked). Returns
     * the associated data row if successful, null otherwise.
     *
     * @param  string  $table  Database table name.
     * @param  string  $token  Plaintext token (64-char hex).
     * @param  array<string, mixed>  $extraUpdate  Additional columns to update on consumption.
     * @return array<string, mixed>|null The token row, or null if invalid/consumed.
     */
    public function consume(string $table, string $token, array $extraUpdate = []): ?array
    {
        $digest = $this->digest($token);

        return DB::transaction(function () use ($table, $digest, $extraUpdate): ?array {
            // Lock the row for atomic consumption
            $row = DB::table($table)
                ->where('token_digest', $digest)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            // Check if already consumed
            if ($row->consumed_at !== null) {
                return null;
            }

            // Check if revoked
            if (isset($row->revoked) && (int) $row->revoked === 1) {
                return null;
            }

            // Check if expired
            if ($row->expires_at !== null && now()->gt($row->expires_at)) {
                return null;
            }

            // Mark as consumed — extraUpdate takes precedence over default
            $update = array_merge([
                'consumed_at' => now()->toDateTimeString(),
            ], $extraUpdate);

            DB::table($table)
                ->where('id', $row->id)
                ->whereNull('consumed_at')
                ->update($update);

            // Re-fetch to get the updated consumed_at
            $updated = DB::table($table)->where('id', $row->id)->first();

            return $updated !== null ? (array) $updated : (array) $row;
        });
    }

    /**
     * Verify a token without consuming it.
     *
     * Returns the associated data row if the token is valid (exists,
     * not consumed, not revoked, not expired), null otherwise.
     *
     * @param  string  $table  Database table name.
     * @param  string  $token  Plaintext token (64-char hex).
     * @return array<string, mixed>|null The token row, or null if invalid.
     */
    public function verify(string $table, string $token): ?array
    {
        $digest = $this->digest($token);

        $row = DB::table($table)
            ->where('token_digest', $digest)
            ->first();

        if ($row === null) {
            return null;
        }

        if ($row->consumed_at !== null) {
            return null;
        }

        if (isset($row->revoked) && (int) $row->revoked === 1) {
            return null;
        }

        if ($row->expires_at !== null && now()->gt($row->expires_at)) {
            return null;
        }

        return (array) $row;
    }

    /**
     * Revoke a token by its digest.
     */
    public function revoke(string $table, string $token): bool
    {
        $digest = $this->digest($token);

        return DB::table($table)
            ->where('token_digest', $digest)
            ->update(['revoked' => 1]) > 0;
    }

    /**
     * Store a new token digest in the given table.
     *
     * @param  string  $table  Database table name.
     * @param  string  $token  Plaintext token (64-char hex).
     * @param  array<string, mixed>  $extra  Additional columns to insert.
     * @return int The inserted row ID.
     */
    public function store(string $table, string $token, array $extra = []): int
    {
        $digest = $this->digest($token);
        $expiresAt = now()->addSeconds(self::DEFAULT_EXPIRY)->toDateTimeString();

        return (int) DB::table($table)->insertGetId(array_merge([
            'token_digest' => $digest,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'revoked' => 0,
            'created_at' => now()->toDateTimeString(),
        ], $extra));
    }

    /**
     * Verify a legacy token (md5-based, 32-char hex).
     *
     * Legacy tokens are stored directly in the hash column (not as
     * digests). This method checks the hash column directly for
     * backward compatibility with existing invite links.
     *
     * @param  string  $table  Database table name.
     * @param  string  $hashColumn  Column name storing the legacy hash.
     * @param  string  $token  Legacy token (32-char hex).
     * @return array<string, mixed>|null The token row, or null if not found.
     */
    public function verifyLegacy(string $table, string $hashColumn, string $token): ?array
    {
        $row = DB::table($table)
            ->where($hashColumn, $token)
            ->first();

        return $row !== null ? (array) $row : null;
    }
}
