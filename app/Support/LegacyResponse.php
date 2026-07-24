<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Temporary Phase 5 migration shim for legacy error / gate helpers.
 *
 * The procedural helpers
 *
 *   - `stderr()`          legacy error page with stdhead/stdfoot/die
 *   - `permissiondenied()`  permission-denied error page
 *   - `int_check()`       positive-integer validation that aborts on failure
 *   - `user_can_upload()` upload permission gate that aborts on deny-limit
 *
 * are collected here because they all share the same side-effect contract
 * (stdhead, stdfoot, die / HttpResponseException). They will be dissolved
 * into context-appropriate services once the legacy bootstrap is gone.
 */
final class LegacyResponse
{
    /**
     * Render a legacy error page and stop execution.
     *
     * Mirrors the old `stderr()` helper from `include/functions.php`:
     * in Laravel context it throws an HttpResponseException carrying the
     * rendered frame; in legacy context it `echo`s and `die`s.
     */
    public static function abort(
        string $heading,
        string $text,
        bool $htmlstrip = true,
        bool $head = true,
        bool $foot = true,
        bool $die = true,
    ): void {
        if ($die && ! (defined('IN_NEXUS') && IN_NEXUS)) {
            ob_start();
            if ($head) {
                \stdhead();
            }
            echo Frame::stdMessage($heading, $text, $htmlstrip);
            if ($foot) {
                \stdfoot();
            }
            $html = (string) ob_get_clean();

            throw new HttpResponseException(new Response($html));
        }

        if ($head) {
            \stdhead();
        }
        echo Frame::stdMessage($heading, $text, $htmlstrip);
        if ($foot) {
            \stdfoot();
        }
        if ($die) {
            exit;
        }
    }

    /**
     * Render the legacy permission-denied page.
     */
    public static function permissionDenied(?int $allowMinimumClass = null): void
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];

        if ($allowMinimumClass === null) {
            self::abort(
                (string) ($lang_functions['std_error'] ?? ''),
                (string) ($lang_functions['std_permission_denied'] ?? ''),
            );

            return;
        }

        self::abort(
            (string) ($lang_functions['std_sorry'] ?? ''),
            (string) ($lang_functions['std_permission_denied_only'] ?? '')
                .UserClass::name($allowMinimumClass, false, true, true)
                .\sprintf((string) ($lang_functions['std_or_above_can_view'] ?? ''), Setting::getSiteName()),
            false,
        );
    }

    /**
     * Validate that a value is a positive integer, aborting on failure.
     *
     * @param  mixed  $value  Single value or array of values.
     * @return true
     */
    public static function assertId(
        mixed $value,
        bool $stdhead = false,
        bool $stdfoot = true,
        bool $die = true,
        bool $log = true,
    ): bool {
        if (is_array($value)) {
            foreach ($value as $val) {
                self::assertId($val, $stdhead, $stdfoot, $die, $log);
            }

            return true;
        }

        if (Validators::isId($value)) {
            return true;
        }

        $CURUSER = $GLOBALS['CURUSER'] ?? [];
        $lang_functions = $GLOBALS['lang_functions'] ?? [];

        $msg = 'Invalid ID Attempt: Username: '.($CURUSER['username'] ?? '')
            .' - UserID: '.($CURUSER['id'] ?? '')
            .' - UserIP : '.(\function_exists('getip') ? \getip() : '');

        if ($log && \function_exists('write_log')) {
            \write_log($msg, 'mod');
        }
        if (\function_exists('do_log')) {
            \do_log($msg, 'error');
        }

        if ($stdhead) {
            self::abort(
                (string) ($lang_functions['std_error'] ?? ''),
                (string) ($lang_functions['std_invalid_id'] ?? ''),
            );
        } else {
            echo '<h2>'.\htmlspecialchars((string) ($lang_functions['std_error'] ?? '')).'</h2>'
                .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
                .\htmlspecialchars((string) ($lang_functions['std_invalid_id'] ?? ''))
                .'</td></tr></table>';
        }

        if ($stdfoot && \function_exists('stdfoot')) {
            \stdfoot();
        }
        if ($die) {
            exit;
        }

        return true;
    }

    /**
     * Legacy upload permission gate.
     *
     * Returns `true` if the current user may upload, `false` if not.
     * Aborts with an error page if the approval-deny limit is reached.
     */
    public static function canUpload(string $where = 'torrents'): bool
    {
        $CURUSER = $GLOBALS['CURUSER'] ?? [];
        $lang_functions = $GLOBALS['lang_functions'] ?? [];

        if (($CURUSER['uploadpos'] ?? '') != 'yes') {
            return false;
        }

        $uploadDenyApprovalDenyCount = (int) \get_setting('main.upload_deny_approval_deny_count');
        $approvalDenyCount = Torrent::query()
            ->where('owner', $CURUSER['id'] ?? 0)
            ->where('approval_status', Torrent::APPROVAL_STATUS_DENY)
            ->count();

        if ($uploadDenyApprovalDenyCount > 0 && $approvalDenyCount >= $uploadDenyApprovalDenyCount) {
            self::abort(
                (string) ($lang_functions['std_sorry'] ?? ''),
                \sprintf((string) ($lang_functions['approval_deny_reach_upper_limit'] ?? ''), $uploadDenyApprovalDenyCount),
                false,
            );
        }

        if ($where === 'torrents') {
            $offerSkipApprovedCount = (int) \get_setting('main.offer_skip_approved_count');
            if (($CURUSER['offer_allowed_count'] ?? 0) >= $offerSkipApprovedCount) {
                return true;
            }
            if (\user_can('upload')) {
                return true;
            }
            if (Time::isWeekendUploadOpen(Setting::getIsUploadOpenAtWeekend(), \time())) {
                return true;
            }
        }

        if ($where === 'music') {
            $enablespecial = $GLOBALS['enablespecial'] ?? '';
            if ($enablespecial === 'yes' && \user_can('uploadspecial')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render a legacy "bark" page — stdhead, a heading, a paragraph,
     * stdfoot, then exit.
     *
     * Mirrors `genbark($x, $y)`.
     */
    public static function bark(string $title, string $message): void
    {
        \stdhead($title);
        echo '<h1>' . \htmlspecialchars($title) . "</h1>\n";
        echo '<p>' . \htmlspecialchars($message) . "</p>\n";
        \stdfoot();
        exit;
    }

    /**
     * Emit a 404 Not Found response and exit.
     *
     * Mirrors `httperr()`.
     */
    public static function notFound(): void
    {
        header('HTTP/1.1 404 Not found');
        echo "<h1>Not Found</h1>\n";
        exit;
    }

    /**
     * Legacy redirect helper. Prepend scheme/host to relative URLs and
     * exit (or throw an HttpResponseException in Laravel context).
     */
    public static function redirect(string $url): void
    {
        if (substr($url, 0, 4) != 'http') {
            $url = \getSchemeAndHttpHost() . '/' . trim($url, '/');
        }

        if (headers_sent()) {
            echo "<script type=\"text/javascript\">window.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
            exit;
        }

        if (! (defined('IN_NEXUS') && IN_NEXUS)) {
            throw new HttpResponseException(new RedirectResponse($url));
        }

        header("Location: $url", true, 302);
        exit;
    }
}
