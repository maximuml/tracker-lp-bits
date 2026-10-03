<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\CleanupServiceInterface;
use Illuminate\Support\Facades\App;

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

        $useCronTriggerCleanUp = (bool) Globals::instance()->get('useCronTriggerCleanUp', false);

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
     * Mirrors `autoclean($printProgress)`. Skipped under PHPUnit: the
     * `lastcleantime*` gate timestamps live in `avps` rows that
     * DatabaseTransactions rolls back, so autoclean would fire on every
     * test request — nondeterministic query counts and fixture mutations.
     * Cleanup itself stays covered by direct `runAll()` tests.
     */
    public static function autoClean(bool $printProgress = false): string|bool
    {
        if (App::runningUnitTests()) {
            return false;
        }

        return app(CleanupServiceInterface::class)->runAll(false, $printProgress);
    }
}
