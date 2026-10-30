<?php

declare(strict_types=1);

namespace App\Support\State;

/**
 * Marker contract for per-request state holders.
 *
 * Implementations live in App\Support\State, are `final`, carry the
 * container `#[Scoped]` attribute, and are flushed between requests,
 * queue jobs and worker cycles via `forgetScopedInstances()` (ResetNexus
 * listener). `reset()` covers explicit flushes where a full scope drop
 * is not wanted (tests, mid-request invalidation).
 *
 * New mutable per-request state must be added to an implementing holder
 * — never to a `static` property elsewhere (enforced by
 * RequestScopedStateRatchetTest + MutableStaticStateRatchetTest).
 */
interface RequestScopedState
{
    public function reset(): void;
}
