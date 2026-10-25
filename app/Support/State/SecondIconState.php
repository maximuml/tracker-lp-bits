<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Per-request static state for SecondIconResource — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class SecondIconState
{
    /** @var array<string, Collection<int|string, mixed>> */
    public array $taxonomyList = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->taxonomyList = [];
    }
}
