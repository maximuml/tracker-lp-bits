<?php

namespace App\Jobs;

use App\Repositories\IpLogRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SaveIpLogCacheToDB implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

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
        \App\Support\Logger::writeWithContext((string) "done", (string) 'info', (bool) false);
    }
}
