<?php

declare(strict_types=1);

namespace App\Support\State;

use App\Models\Tag;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Per-request static state for TagRepository — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class TagRepositoryState implements RequestScopedState
{
    public ?string $orderByFieldIdString = null;

    /** @var EloquentCollection<int, Tag>|null */
    public ?EloquentCollection $allTags = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->orderByFieldIdString = null;
        $this->allTags = null;
    }
}
