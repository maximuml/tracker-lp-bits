<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\CleanupRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckQueueFailedJobs implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    /** @var int */
    public $uniqueFor = 3600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function uniqueId(): string
    {
        return self::class;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CleanupRepository $cleanupRepository)
    {
        $cleanupRepository->checkQueueFailedJobs();
        Logger::writeWithContext((string) 'checkQueueFailedJobs run success.', (string) 'info', (bool) false);
    }
}
