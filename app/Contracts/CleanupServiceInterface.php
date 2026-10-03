<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for the cleanup orchestrator — `final` CleanupService cannot be
 * faked by test doubles, so injection seams type-hint this interface.
 */
interface CleanupServiceInterface
{
    public function triggerCron(): string;

    public function runFull(bool $forceAll = false, bool $printProgress = true): string;

    public function runAll(bool $forceAll = false, bool $printProgress = false): string|bool;
}
