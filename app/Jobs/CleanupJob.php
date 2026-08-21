<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CleanupService;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue wrapper for the periodic cleanup orchestrator.
 *
 * Replaces the synchronous `cleanup:run` schedule so cleanup runs in the
 * Horizon queue instead of inside the scheduler container.
 */
final class CleanupJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 3600;

    /** @var int */
    public $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return self::class;
    }

    public function handle(CleanupService $service): void
    {
        $result = $service->runAll(false, false);

        if ($result === false) {
            Logger::writeWithContext((string) '[CleanupJob] cleanup not triggered.', (string) 'info', (bool) false);

            return;
        }

        Logger::writeWithContext((string) '[CleanupJob] cleanup finished.', (string) 'info', (bool) false);
    }
}
