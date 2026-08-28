<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\TorrentRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadTorrentBoughtUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $torrentId;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $torrentId)
    {
        $this->torrentId = $torrentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $rep = app(TorrentRepository::class);
        $result = $rep->loadBoughtUser($this->torrentId);
        Logger::writeWithContext((string) "result: {$result}", (string) 'info', (bool) false);
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Logger::writeWithContext((string) ('failed: '.$exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
    }
}
