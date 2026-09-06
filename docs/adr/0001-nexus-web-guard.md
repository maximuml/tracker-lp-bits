# ADR 0001: NexusWebGuard for cookie-based auth

## Status

Accepted (Sprint 0, carried forward from NexusPHP fork)

## Context

NexusPHP uses a custom cookie-based authentication system with a signed
`c_secure_pass` cookie. The cookie contains the user ID and an expiry
timestamp, encrypted with the application key (or HMAC-signed with the
user's `auth_key` in the legacy format).

When the project was migrated to Laravel, the standard `session` guard
was insufficient because:

1. Existing users had `c_secure_pass` cookies that needed to be accepted
   without forcing a re-login.
2. The tracker's BitTorrent clients authenticate via passkey (not sessions),
   requiring a separate guard.
3. The legacy `auth_key` per-user HMAC token format needed backward
   compatibility during the migration window.

## Decision

Implement a custom `NexusWebGuard` implementing `StatefulGuard` that:

- Reads the `c_secure_pass` cookie via `AuthCookie::verifyToken()`.
- Falls back to legacy HMAC verification using `users.auth_key`.
- Returns the `User` model via `NexusWebUserProvider`.
- Registers as the `nexus-web` guard in `config/auth.php`.

A separate `passkey` guard handles BitTorrent client authentication.

## Consequences

- **Positive:** Existing user sessions survive migration; no forced logouts.
- **Positive:** Clear separation between web (cookie), API (Sanctum), and
  tracker (passkey) authentication.
- **Negative:** Custom guard requires maintenance and security review;
  standard Laravel auth features (password confirm, session regeneration)
  need manual implementation.
- **Negative:** Legacy HMAC fallback (`AuthCookie::verifyToken` with
  `$authKey`) adds complexity and should be removed once all cookies have
  rotated to the encrypted format (W1-04 in the new plan).
