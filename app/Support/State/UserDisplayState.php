<?php

declare(strict_types=1);

namespace App\Support\State;

use App\Support\Html\SafeHtml;
use Illuminate\Container\Attributes\Scoped;

/**
 * Scoped per-request state for App\Support\UserDisplay — the row and
 * rendered-username caches. Dropped by forgetScopedInstances() between
 * jobs/worker cycles; the facade's resetState() still runs first so the
 * Redis "user_{id}_content" invalidation fires while the ids are known.
 */
#[Scoped]
final class UserDisplayState
{
    /** @var array<int|string, array<int|string, mixed>|false> */
    public array $rowCache = [];

    /** @var array<int, SafeHtml> */
    public array $usernameCache = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->rowCache = [];
        $this->usernameCache = [];
    }
}
