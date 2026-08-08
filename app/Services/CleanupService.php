<?php

namespace App\Services;

use App\Support\SupportContext;

/**
 * Legacy cleanup runner extracted from `include/cleanup.php`.
 *
 * Wraps `autoclean()`/`docleanup()` so the cron and manual cleanup endpoints
 * no longer need Blade partials that `extract($GLOBALS, EXTR_SKIP)`.
 */
final class CleanupService
{
    /**
     * Trigger the periodic (cron) cleanup if cron-triggered cleanup is enabled.
     *
     * Mirrors the legacy `public/cron.php` + `resources/views/cron/_cron_legacy.php`.
     */
    public function triggerCron(): string
    {
        $useCronTriggerCleanUp = (bool) SupportContext::getGlobal('useCronTriggerCleanUp', true);

        if (! $useCronTriggerCleanUp) {
            return "Forbidden. Clean-up is set to be browser-triggered.\n";
        }

        $result = \autoclean(false);

        if ($result === false || $result === '') {
            return "Clean-up not triggered.\n";
        }

        return (string) $result . "\n";
    }

    /**
     * Run the full cleanup routine (sysop-only manual trigger).
     *
     * Mirrors the legacy `docleanup.php` flow. The returned string already
     * contains the progress HTML emitted by `docleanup()`.
     */
    public function runFull(bool $forceAll = false, bool $printProgress = true): string
    {
        if (! \app()->runningInConsole() && \get_user_class() < \constant('UC_SYSOP')) {
            return 'forbidden';
        }

        if (! \function_exists('docleanup')) {
            require_once \base_path('app/Support/Legacy/cleanup.php');
        }

        $tstart = \getmicrotime();

        \ob_start();
        $result = \docleanup($forceAll ? 1 : 0, $printProgress);
        $progress = \ob_get_clean();

        $tend = \getmicrotime();

        $html = '<html><head><title>Do Clean-up</title></head><body>';
        $html .= '<p>clean-up in progress...please wait<br />';
        if (! $forceAll) {
            $html .= 'If you need to force a complete cleaning, click <a href="docleanup.php?forceall=1">here</a><br />';
        }
        $html .= '</p>';
        $html .= $progress;
        if ($result !== false && $result !== '') {
            $html .= '<p>' . \htmlspecialchars((string) $result) . '</p>';
        }
        $html .= \sprintf('Time consumed：%f sec<br />', $tend - $tstart);
        $html .= 'Done<br />';
        $html .= '</body></html>';

        return $html;
    }
}
