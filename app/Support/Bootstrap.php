<?php

namespace App\Support;

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
        $useCronTriggerCleanUp = (bool) SupportContext::getGlobal('useCronTriggerCleanUp', false);

        \Nexus\Database\NexusDB::getInstance()->autoConnect();

        if ($doLogin) {
            \userlogin();
        }

        if (! $useCronTriggerCleanUp && $autoclean) {
            register_shutdown_function('autoclean');
        }
    }

    /**
     * Run the legacy periodic cleanup tasks.
     *
     * Mirrors `autoclean($printProgress)`.
     */
    public static function autoClean(bool $printProgress = false): string|bool
    {
        $autoclean_interval_one = (int) SupportContext::getGlobal('autoclean_interval_one', 900);
        $rootpath = (string) SupportContext::getGlobal('rootpath', dirname(__DIR__, 2) . '/');

        $now = TIMENOW;
        $ts = (int) \Nexus\Database\NexusDB::table('avps')->where('arg', 'lastcleantime')->value('value_u');

        if ($ts === 0) {
            \do_log("SELECT value_u FROM avps WHERE arg = 'lastcleantime', empty");
            \Nexus\Database\NexusDB::table('avps')->insert(['arg' => 'lastcleantime', 'value_u' => $now]);

            return false;
        }

        if ($ts + $autoclean_interval_one > $now) {
            \do_log("ts: {$ts} + autoclean_interval_one: $autoclean_interval_one > now: $now");

            return false;
        }

        $updated = \Nexus\Database\NexusDB::table('avps')
            ->where('arg', 'lastcleantime')
            ->where('value_u', $ts)
            ->update(['value_u' => $now]);

        if (! $updated) {
            \do_log("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts, affectedRows = 0");

            return false;
        }

        require_once $rootpath . 'include/cleanup.php';

        return docleanup(0, $printProgress);
    }
}
