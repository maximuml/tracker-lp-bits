<?php

declare(strict_types=1);

namespace App\Support\State;

use Illuminate\Container\Attributes\Scoped;

/**
 * Scoped memo for App\Repositories\TorrentDownloadRepository — hkdf
 * downhash derivations keyed by passkey. A process-global memo would
 * serve stale hashes after a passkey rotation; scoped lifetime bounds
 * the memo to the current request/job.
 */
#[Scoped]
final class TorrentDownloadState implements RequestScopedState
{
    /** @var array<string, string> */
    public array $downHashKeys = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function reset(): void
    {
        $this->downHashKeys = [];
    }
}
