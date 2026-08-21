<?php

namespace App\Support;

use App\Services\CleanupService;
use Nexus\Database\NexusDB;

/**
 * Legacy bootstrap/cleanup helpers drained out of `include/functions.php`.
 *
 * These mutate global state and are tightly coupled to the legacy request
 * lifecycle, so they live here as a migration shim rather than in a service.
 */
final class Bootstrap
{
    /**
     * Connect to the database and optionally trigger the legacy user-login
     * and autoclean registration.
     *
     * Mirrors `dbconn($autoclean, $doLogin)`.
     */
    public static function connect(bool $autoclean = false, bool $doLogin = true): void
    {
        // Reset the per-request context so legacy login reads the correct
        // request/cookie values, not stale FPM worker state from a previous request.
        SupportContext::reset();

        $useCronTriggerCleanUp = (bool) SupportContext::getGlobal('useCronTriggerCleanUp', false);

        NexusDB::getInstance()->autoConnect();

        if ($doLogin) {
            LegacyAuth::loginFromContext();
        }

        if (! $useCronTriggerCleanUp && $autoclean) {
            register_shutdown_function([self::class, 'autoClean']);
        }
    }

    /**
     * Run the legacy periodic cleanup tasks.
     *
     * Mirrors `autoclean($printProgress)`.
     */
    public static function autoClean(bool $printProgress = false): string|bool
    {
        return app(CleanupService::class)->runAll(false, $printProgress);
    }
}
