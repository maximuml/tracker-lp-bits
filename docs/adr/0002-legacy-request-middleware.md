# ADR 0002: LegacyRequestMiddleware for URL rewriting

## Status

Accepted (Sprint 17, Octane-safe in T-11)

## Context

NexusPHP URLs follow the pattern `/details.php?id=5`, `/torrents.php`,
`/index.php`, etc. The Laravel migration needed to route these to
controllers while preserving all existing user bookmarks, search engine
links, and `.torrent` files with embedded announce URLs.

Options considered:
1. **`.htaccess` / nginx rewrite rules** — fragile, not testable, differs
   per web server.
2. **Route pattern matching** — would require hundreds of explicit routes
   for every legacy script name.
3. **Global middleware that rewrites `REQUEST_URI`** — runs once per
   request, is Octane-compatible, and keeps rewrite logic in PHP where it
   can be tested.

## Decision

Implement `LegacyRequestMiddleware` as a global middleware that:

- Detects the executed script name from `SCRIPT_FILENAME` / `SCRIPT_NAME`.
- Rewrites `/foo.php?id=5` to `/foo?id=5` (or `/foo/5` for specific routes).
- Skips Laravel-only paths (`api/`, `livewire/`, `filament/`, `horizon/`,
  `nexusphp/`, `web/`).
- Boots the legacy context (`LegacyBootstrap::boot`) for cache, settings,
  and language.
- Handles Octane workers (RoadRunner/FrankenPHP/Swoole) by treating worker
  scripts as `index.php`.

## Consequences

- **Positive:** All legacy URLs continue to work without web-server config.
- **Positive:** Octane-compatible (reset per request via `CurrentUser::reset()`).
- **Positive:** Testable via `LegacySmokeTest` and `LegacyHeaderIsolationTest`.
- **Negative:** Every request pays the regex-rewrite cost (~0.1ms).
- **Negative:** The middleware is 200+ lines and handles many edge cases
  (`confirmemail` path segments, `details` → `torrent` mapping, etc.).
- **Future:** W2-11 in the new plan proposes moving URL rewriting to
  `RouteServiceProvider` or OpenResty, leaving only context bootstrap in
  the middleware.
