<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Request-scoped replacement for the process-global legacy/tracker
 * entry-point constants (ADR 0017).
 *
 * `isLegacy()` is true when the request entered through the web front
 * controller (legacy FPM semantics), false for Octane worker boot, console
 * and test processes. `isTracker()` is true on the announce/scrape hot path.
 *
 * The constructor captures the entry-point defaults; `reset()` restores
 * them, so `ResetNexus` can flush per-request mutations (and tests can flip
 * the flag per test) without leaking state across Octane worker requests —
 * the exact hazard a process-global constant could not express.
 */
final class LegacyRuntime
{
    private bool $entryLegacy;

    private bool $entryTracker;

    private bool $legacy;

    private bool $tracker;

    public function __construct(bool $entryLegacy = false, bool $entryTracker = false)
    {
        $this->entryLegacy = $entryLegacy;
        $this->entryTracker = $entryTracker;
        $this->reset();
    }

    public function isLegacy(): bool
    {
        return $this->legacy;
    }

    public function isTracker(): bool
    {
        return $this->tracker;
    }

    public function markLegacy(bool $on = true): void
    {
        $this->legacy = $on;
    }

    public function markTracker(bool $on = true): void
    {
        $this->tracker = $on;
    }

    /**
     * Update the entry-point defaults (entry scripts and test suites).
     */
    public function bootEntry(bool $legacy, bool $tracker = false): void
    {
        $this->entryLegacy = $legacy;
        $this->entryTracker = $tracker;
        $this->reset();
    }

    /**
     * Restore the entry-point state captured at construction.
     */
    public function reset(): void
    {
        $this->legacy = $this->entryLegacy;
        $this->tracker = $this->entryTracker;
    }
}
