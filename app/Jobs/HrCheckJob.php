<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\HitAndRunRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue wrapper for Hit & Run penalty checks.
 *
 * Replaces the synchronous `hr:update_status` artisan command so H&R status
 * updates are processed by Horizon workers.
 */
final class HrCheckJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 1800;

    public int $backoff = 60;

    /** @var int */
    public $uniqueFor = 1800;

    public function __construct(
        private readonly ?int $uid = null,
        private readonly ?int $torrentId = null,
        private readonly bool $ignoreTime = false,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return self::class.':'.($this->ignoreTime ? 'ignore_time' : 'default');
    }

    public function handle(HitAndRunRepository $repository): void
    {
        $repository->cronjobUpdateStatus($this->uid, $this->torrentId, $this->ignoreTime);

        Logger::writeWithContext(
            (string) sprintf(
                '[HrCheckJob] H&R check finished. uid: %s, torrentId: %s, ignoreTime: %s',
                $this->uid ?? 'null',
                $this->torrentId ?? 'null',
                $this->ignoreTime ? '1' : '0',
            ),
            (string) 'info',
            (bool) false,
        );
    }
}
