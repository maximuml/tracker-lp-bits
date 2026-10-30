<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request static state for Setting model — container-flushed between
 * queue jobs / worker cycles via forgetScopedInstances(). Access
 * through instance(); reset() covers explicit flushes.
 */
#[Scoped]
final class SettingState implements RequestScopedState
{
    /** @var array<string, mixed>|null */
    public ?array $autoloadedSettings = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->autoloadedSettings = null;
    }
}
