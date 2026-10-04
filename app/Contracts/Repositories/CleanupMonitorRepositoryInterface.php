<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface CleanupMonitorRepositoryInterface
{
    public function checkCleanup(): void;

    public function checkQueueFailedJobs(): void;

    public function deleteFailedJobsBefore(string $until): int;
}
