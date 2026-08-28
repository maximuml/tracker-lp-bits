<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\AttendanceRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue wrapper for attendance record cleanup.
 *
 * Replaces the synchronous `attendance:cleanup` artisan command so the
 * daily attendance pruning runs in the Horizon queue.
 */
final class AttendanceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 1800;

    public int $backoff = 60;

    /** @var int */
    public $uniqueFor = 86400;

    public function uniqueId(): string
    {
        return self::class;
    }

    public function handle(AttendanceRepository $repository): void
    {
        $deleted = $repository->cleanup();

        Logger::writeWithContext(
            (string) sprintf('[AttendanceJob] attendance cleanup finished, deleted: %d', $deleted),
            (string) 'info',
            (bool) false,
        );
    }
}
