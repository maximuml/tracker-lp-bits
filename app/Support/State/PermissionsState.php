<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for Permissions — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class PermissionsState implements RequestScopedState
{
    /** @var array<int|string, mixed> */
    public array $userCanCache = [];

    public int $sequence = 0;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->userCanCache = [];
        $this->sequence = 0;
    }
}
