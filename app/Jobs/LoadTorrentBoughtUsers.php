<?php

namespace App\Jobs;

use App\Repositories\TorrentRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadTorrentBoughtUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $torrentId;

    public $tries = 1;

    public $timeout = 1800;

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
        $rep = new TorrentRepository();
        $result = $rep->loadBoughtUser($this->torrentId);
        \App\Support\Logger::writeWithContext((string) "result: {$result}", (string) 'info', (bool) false);
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        \App\Support\Logger::writeWithContext((string) ("failed: " . $exception->getMessage() . $exception->getTraceAsString()), (string) 'error', (bool) false);
    }
}
