# ADR 0003: SiteConfig typed configuration

## Status

Accepted (Sprint 20)

## Context

NexusPHP stores all site configuration in a `settings` table as key/value
pairs with dot-separated prefixes (`main.sitename`, `torrent.allowreq`,
`security.iv`, etc.). The legacy code accessed these via `Globals::get()`
which returned untyped `mixed` values, leading to:

- PHPStan level 8 violations (untyped returns).
- Runtime errors from unexpected types (`'yes'` vs `true`).
- No IDE autocompletion for configuration keys.
- No validation of configuration values.

## Decision

Implement `App\Support\Config\SiteConfig` as a typed configuration facade:

- `SiteConfig::current()` returns a cached `MainConfig` instance.
- Each prefix (`main.`, `torrent.`, `security.`, `bonus.`, etc.) has a
  dedicated config class (`BasicConfig`, `TorrentConfig`, `SecurityConfig`,
  `BonusConfig`, etc.) with typed accessor methods.
- Values are cast to proper types (`bool`, `int`, `string`, `enum`) on read.
- The underlying `Globals` singleton still reads from the `settings` table
  and Redis cache.

## Consequences

- **Positive:** PHPStan level 8 compliance for configuration access.
- **Positive:** IDE autocompletion and type safety.
- **Positive:** Enum-based config values (e.g. `SecurityConfig::loginType()`
  returns `LoginType` enum) prevent invalid comparisons.
- **Negative:** Each new setting requires adding a method to the appropriate
  config class.
- **Negative:** The `Globals` singleton is still used internally — full
  removal is deferred to W3-03 (settings schema validation).
