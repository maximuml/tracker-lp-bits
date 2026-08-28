<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\CleanupRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCleanup
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CleanupRepository::checkCleanup();
        Logger::writeWithContext((string) 'CheckCleanup job run success.', (string) 'info', (bool) false);
    }
}
