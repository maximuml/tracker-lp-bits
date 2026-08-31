<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Contracts;

/**
 * Contract for a single self-contained cleanup operation.
 *
 * Each implementation performs one idempotent cleanup and returns a short
 * log message. Callers are responsible for locking and scheduling.
 */
interface CleanupTask
{
    public function run(): string;
}
