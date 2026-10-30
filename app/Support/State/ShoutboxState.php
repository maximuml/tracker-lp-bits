<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for Shoutbox — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class ShoutboxState implements RequestScopedState
{
    /** @var array<int|string, array<string, mixed>|false> */
    public array $mentionUserCache = [];

    /** @var array<int, bool> */
    public array $torrentExistsCache = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->mentionUserCache = [];
        $this->torrentExistsCache = [];
    }
}
