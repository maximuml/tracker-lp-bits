<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Permission;
use App\Models\User;
use App\Repositories\TorrentRepository;
use App\Support\Config\SiteConfig;
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
    /**
     * Print a legacy SQL error page and stop execution.
     *
     * Backs the `sqlerr()` helper.
     */
    public static function sqlError(string $file, string $line): void
    {
        throw new HttpResponseException(new Response(Frame::sqlError(LegacyDb::error(), $file, $line), 500));
    }

    public static function abort(
        string $heading,
        string $text,
        bool $htmlstrip = true,
        bool $head = true,
        bool $foot = true,
        bool $die = true,
    ): void {
        if (! $die) {
            if ($head) {
                Html::stdhead();
            } elseif ($foot && PageLayout::getContext() === null) {
                // Ensure a PageLayout context exists for stdfoot() even when the
                // caller requested no header (e.g. permission denied before stdhead).
                ob_start();
                Html::stdhead();
                ob_end_clean();
            }
            echo Frame::stdMessage($heading, $text, $htmlstrip);
            if ($foot) {
                Html::stdfoot();
            }

            return;
        }

        $level = ob_get_level();
        ob_start();
        try {
            if ($head) {
                Html::stdhead();
            } elseif ($foot && PageLayout::getContext() === null) {
                // Ensure a PageLayout context exists for stdfoot() even when the
                // caller requested no header (e.g. permission denied before stdhead).
                ob_start();
                Html::stdhead();
                ob_end_clean();
            }
            echo Frame::stdMessage($heading, $text, $htmlstrip);
            if ($foot) {
                Html::stdfoot();
            }
            $html = (string) ob_get_clean();
        } catch (HttpResponseException $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        throw new HttpResponseException(new Response($html));
    }

    /**
     * Render the legacy permission-denied page.
     */
    public static function permissionDenied(?int $allowMinimumClass = null): void
    {
        $lang_functions = app(Language::class)->functions();

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
                .\sprintf((string) ($lang_functions['std_or_above_can_view'] ?? ''), SiteConfig::current()->basic->siteName()),
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

        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $lang_functions = app(Language::class)->functions();

        $msg = 'Invalid ID Attempt: Username: '.($CURUSER['username'] ?? '')
            .' - UserID: '.($CURUSER['id'] ?? '')
            .' - UserIP : '.(Network::clientIp());

        if ($log && \function_exists('write_log')) {
            Log::writeWithContext($msg, 'mod');
        }
        if (\function_exists('do_log')) {
            Logger::writeWithContext($msg, 'error');
        }

        if ($stdhead) {
            self::abort(
                (string) ($lang_functions['std_error'] ?? ''),
                (string) ($lang_functions['std_invalid_id'] ?? ''),
            );

            return true;
        }

        $errorHtml = '<h2>'.\htmlspecialchars((string) ($lang_functions['std_error'] ?? '')).'</h2>'
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            .\htmlspecialchars((string) ($lang_functions['std_invalid_id'] ?? ''))
            .'</td></tr></table>';

        if ($die) {
            $level = ob_get_level();
            ob_start();
            try {
                if ($stdfoot) {
                    Html::stdhead();
                }
                echo $errorHtml;
                if ($stdfoot) {
                    Html::stdfoot();
                }
                $html = (string) ob_get_clean();
            } catch (HttpResponseException $e) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                throw $e;
            }

            throw new HttpResponseException(new Response($html));
        }

        if ($stdfoot && \function_exists('stdfoot')) {
            Html::stdfoot();
        }

        echo $errorHtml;

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
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $lang_functions = app(Language::class)->functions();

        if (! ($CURUSER['uploadpos'] ?? true)) {
            return false;
        }

        $uploadDenyApprovalDenyCount = (int) SiteConfig::current()->main->uploadDenyApprovalDenyCount();
        $approvalDenyCount = TorrentRepository::getApprovalDenyCount((int) ($CURUSER['id'] ?? 0));

        if ($uploadDenyApprovalDenyCount > 0 && $approvalDenyCount >= $uploadDenyApprovalDenyCount) {
            self::abort(
                (string) ($lang_functions['std_sorry'] ?? ''),
                \sprintf((string) ($lang_functions['approval_deny_reach_upper_limit'] ?? '%s'), $uploadDenyApprovalDenyCount),
                false,
            );
        }

        if ($where === 'torrents') {
            $offerSkipApprovedCount = (int) SiteConfig::current()->main->offerSkipApprovedCount();
            if (($CURUSER['offer_allowed_count'] ?? 0) >= $offerSkipApprovedCount) {
                return true;
            }
            if (Permission::canUploadToNormalSection()) {
                return true;
            }
            if (Time::isWeekendUploadOpen(SiteConfig::current()->main->isUploadOpenAtWeekend(), \time())) {
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
        $level = ob_get_level();
        ob_start();
        try {
            Html::stdhead($title);
            echo '<h1>'.\htmlspecialchars($title)."</h1>\n";
            echo '<p>'.\htmlspecialchars($message)."</p>\n";
            Html::stdfoot();
            $html = (string) ob_get_clean();
        } catch (HttpResponseException $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        throw new HttpResponseException(new Response($html));
    }

    /**
     * Emit a 404 Not Found response and exit.
     *
     * Mirrors `httperr()`.
     */
    public static function notFound(): void
    {
        throw new HttpResponseException(new Response("<h1>Not Found</h1>\n", 404));
    }

    /**
     * Legacy redirect helper. Prepend scheme/host to relative URLs and
     * exit (or throw an HttpResponseException in Laravel context).
     */
    public static function redirect(string $url): void
    {
        if (substr($url, 0, 4) != 'http') {
            $url = Url::schemeAndHost().'/'.trim($url, '/');
        }

        if (headers_sent()) {
            throw new HttpResponseException(new Response("<script type=\"text/javascript\">window.location.href = '".htmlspecialchars($url, ENT_QUOTES)."';</script>"));
        }

        throw new HttpResponseException(new RedirectResponse($url, 302));
    }
}
