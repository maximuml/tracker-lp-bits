<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for SearchBox model — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class SearchBoxModelState
{
    /** @var array<int|string, mixed> */
    public array $instances = [];

    /** @var array<int|string, mixed> */
    public array $modeOptions = [];

    /** @var array<int|string, mixed> */
    public array $categoryIdsBySearchBox = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->instances = [];
        $this->modeOptions = [];
        $this->categoryIdsBySearchBox = [];
    }
}
