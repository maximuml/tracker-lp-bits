<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\IpLogRepository;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SaveIpLogCacheToDB implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        IpLogRepository::saveToDB();
        Logger::writeWithContext((string) 'done', (string) 'info', (bool) false);
    }
}
