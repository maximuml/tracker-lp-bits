# ADR 0005: Architecture ratchet tests

## Status

Accepted (W0, this PR)

## Context

The project has accumulated significant technical debt in measurable
areas: legacy view patterns (`{!! !!}`, `@php`, `<table>` layout),
god-object repositories (>500 lines), direct superglobal access, and
mixed-method routes (GET+POST). Previous modernisation sprints reduced
some of these, but without automated enforcement the counts could
silently regress.

Three architecture tests existed (`MixedRouteAllowListTest`,
`ActorContextRatchetTest`, `ViewsHaveNoSideEffectsTest`) but were **not
in any PHPUnit testsuite and not run in CI** — they existed only as
files that could be executed manually.

## Decision

1. **Add an `Architecture` testsuite to `phpunit.xml`** so all
   architecture tests are included in the standard test run.

2. **Run the Architecture suite in CI** (PHP 8.5 + MySQL 9.0 matrix
   cell) to enforce ratchets on every push and PR.

3. **Add new ratchet tests** with baselines captured at the current state:
   - `LegacyViewSurfaceTest`: `{!! !!}` (359), `@php` (279),
     `\App\Support\Html::` (373), `<table>` (220), inline `on*=` (35).
   - `RepositorySizeTest`: 20 files >500 lines in Repositories/Services
     with per-file size tracking.
   - `NoSuperglobalsTest`: 9 lines of actual code using `$_SERVER` etc.
     (excluding comments and Install scripts).

4. **Add a countdown to `MixedRouteAllowListTest`**: `MAX_ALLOWED_ENTRIES`
   constant (68) that must only decrease.

5. **Ratchet semantics**: `assertLessThanOrEqual(baseline, current)` —
   counts may decrease (improvement) but never increase (regression).
   Baselines should be lowered — never raised — as migration progresses.

## Consequences

- **Positive:** Any PR that adds a new `{!! !!}` or a new 600-line
  repository will fail CI.
- **Positive:** Existing ratchets (`ActorContextRatchetTest`,
  `ViewsHaveNoSideEffectsTest`) are now enforced in CI for the first time.
- **Positive:** The countdown on `MixedRouteAllowList` creates a visible
  metric for W1-01 progress.
- **Negative:** Developers must update baseline constants when reducing
  counts (lower the number, don't just leave it).
- **Negative:** False positives possible if a file is renamed or moved —
  the `RepositorySizeTest` checks for baseline file existence and will
  flag removed files.
