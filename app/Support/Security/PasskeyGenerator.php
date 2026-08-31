<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Generates cryptographically secure passkeys for tracker announce.
 *
 * Replaces the legacy `md5($username . date(...) . $passhash)` pattern
 * which was predictable and non-CSPRNG. The new passkey is 32 hex
 * characters (128 bits of entropy from {@see random_bytes}), matching
 * the format expected by the OpenResty Lua filter (`tracker_filter.lua`
 * validates passkey length == 32).
 */
final class PasskeyGenerator
{
    /**
     * Generate a new 32-character hex passkey.
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
