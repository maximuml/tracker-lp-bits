# ADR 0004: Stateless AnnounceService with AnnounceContext

## Status

Accepted (T-18, PR #616)

## Context

The `AnnounceService` is the tracker's hottest path — every BitTorrent
client calls `/announce` every 30–90 seconds. The original implementation
used mutable per-request state spread across multiple properties and
side-effecting method calls, making it:

- Untestable in isolation (state leaked between test runs).
- Prone to Octane cross-request contamination (mutable service properties).
- Difficult to reason about (methods modified `$this->user`, `$this->torrent`,
  etc. as side effects).

## Decision

Refactor `AnnounceService` to use an immutable `AnnounceContext` DTO:

- `AnnounceContext` is a readonly value object with `with*()` methods that
  return new instances (`withUser()`, `withTorrent()`, `withTraffic()`, etc.).
- Each pipeline step (`authenticateUser`, `checkClient`, `loadTorrent`, etc.)
  takes `AnnounceContext` as input and returns a new `AnnounceContext` with
  additional data.
- The service itself has no mutable state — all state flows through the
  context.
- Row locking (`lockRowsForUpdate`) happens inside a `DB::transaction()`
  closure, with the context as a captured variable.
- Sub-components (`TrafficAccountant`, `CheaterDetector`, `HitAndRunHandler`,
  `RateLimiter`, `PeerLifecycle`) are injected via constructor DI and are
  themselves stateless.

## Consequences

- **Positive:** Octane-safe — no mutable service state between requests.
- **Positive:** Each pipeline step is independently testable with a
  constructed `AnnounceContext`.
- **Positive:** Data flow is explicit and traceable (no hidden side effects).
- **Positive:** PHPStan level 8 compliant with full type information.
- **Negative:** `AnnounceContext` is 411 lines with many `with*()` methods —
  verbosity is the cost of immutability.
- **Negative:** The context carries a `ResponseBuilder` which is itself
  mutable (it accumulates response data); a future refinement could make
  the response builder immutable too.
