<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for Forum — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class ForumState
{
    /** @var array<int|string, mixed>|null */
    public ?array $moderatorsArray = null;

    /** @var array<int|string, mixed> */
    public array $topics = [];

    /** @var list<string>|null */
    public ?array $protectedForumIds = null;

    /** @var array<int, int>|null */
    public ?array $forumMods = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->moderatorsArray = null;
        $this->topics = [];
        $this->protectedForumIds = null;
        $this->forumMods = null;
    }
}
