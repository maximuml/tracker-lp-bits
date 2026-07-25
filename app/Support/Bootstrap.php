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
        global $useCronTriggerCleanUp;

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
    public static function autoClean(bool $printProgress = false): bool
    {
        global $autoclean_interval_one, $rootpath;

        $now = TIMENOW;
        $res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
        $row = mysql_fetch_array($res);

        if (! $row) {
            \do_log("SELECT value_u FROM avps WHERE arg = 'lastcleantime', empty");
            sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime',$now)") or sqlerr(__FILE__, __LINE__);

            return false;
        }

        $ts = $row[0];
        if ($ts + $autoclean_interval_one > $now) {
            \do_log("ts: {$ts} + autoclean_interval_one: $autoclean_interval_one > now: $now");

            return false;
        }

        sql_query("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts") or sqlerr(__FILE__, __LINE__);

        if (! mysql_affected_rows()) {
            \do_log("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts, affectedRows = 0");

            return false;
        }

        require_once $rootpath . 'include/cleanup.php';

        return docleanup(0, $printProgress);
    }
}
