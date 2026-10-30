<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for CleanupRepository — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class CleanupRepositoryState implements RequestScopedState
{
    public float|int $oneTaskSeconds = 0;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->oneTaskSeconds = 0;
    }
}
