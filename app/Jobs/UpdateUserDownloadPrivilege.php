<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\UserRepository;
use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateUserDownloadPrivilege implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $userId, public readonly string $status, public readonly string $reasonKey)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $rep = app(UserRepository::class);
        $rep->updateDownloadPrivileges(null, $this->userId, $this->status, $this->reasonKey);
        Logger::writeWithContext((string) "Updating user download privilege for user {$this->userId} to {$this->status} by reason {$this->reasonKey}", (string) 'info', (bool) false);
    }
}
