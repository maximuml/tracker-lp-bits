<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Marker job dispatched by `queue:probe` to prove workers actually run jobs
 * end-to-end (Horizon "running" only proves the supervisor is alive). The job
 * sleeps to stay in flight during graceful-stop drills, then writes its token
 * to the cache so the waiting probe command can confirm completion.
 */
final class QueueProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'queue:probe:token';

    public const META_KEY = 'queue:probe:meta';

    /** @var int */
    public $tries = 1;

    public function __construct(
        private readonly string $token,
        private readonly int $sleepSeconds,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $started = microtime(true);
        // Wall-time loop, not sleep(): SIGTERM interrupts sleep()/usleep()
        // via the worker's async pcntl handlers, which would fake a short
        // job and make drain tests meaningless. A real long job (chunked
        // DB work) is not shortened by a termination signal either.
        if ($this->sleepSeconds > 0) {
            $end = $started + $this->sleepSeconds;
            while (microtime(true) < $end) {
                usleep(100000);
            }
        }
        Cache::put(self::CACHE_KEY, $this->token, 600);
        Cache::put(self::META_KEY, sprintf('%.1fs', microtime(true) - $started), 600);
    }
}
