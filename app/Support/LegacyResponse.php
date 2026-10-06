<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Permission;
use App\Models\User;
use App\Repositories\TorrentModerationRepository;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
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
    ): never {
        $renderer = self::pageRenderer();

        try {
            if ($head) {
                $html = (string) $renderer->headerHtml();
            } else {
                $html = '';
                if ($foot && ! $renderer->hasContext()) {
                    // Context side effect only — the header markup is discarded,
                    // matching the old buffered stdhead() call.
                    $renderer->headerHtml();
                }
            }
            $html .= view('partials.std-message', [
                'heading' => $heading,
                'text' => $text,
                'htmlstrip' => $htmlstrip,
                'body' => null,
            ])->render();
            if ($foot) {
                $html .= (string) $renderer->footerHtml();
            }
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable) {
            // If rendering fails partway through (e.g. missing user data in
            // the test environment), fall back to a minimal shell — mirroring
            // the old inline PHP which emitted partial HTML before throwing.
            if (($html ?? '') === '') {
                $html = trim(view('support._error-shell')->render());
            }
        }

        throw new HttpResponseException(new Response($html));
    }

    /**
     * Render the same error frame `abort()` produces (stdhead + stdMessage
     * + stdfoot) and return it instead of echoing — for controllers that
     * need a Response object rather than an HttpResponseException.
     */
    public static function captureAbort(string $heading, string $text, bool $htmlstrip = true, string $title = ''): string
    {
        $renderer = self::pageRenderer();

        return (string) $renderer->headerHtml($title)
            .view('partials.std-message', [
                'heading' => $heading,
                'text' => $text,
                'htmlstrip' => $htmlstrip,
                'body' => null,
            ])->render()
            .(string) $renderer->footerHtml();
    }

    /**
     * Render the legacy permission-denied page.
     */
    public static function permissionDenied(?int $allowMinimumClass = null): never
    {

        if ($allowMinimumClass === null) {
            self::abort(
                (string) (__('legacy/functions.std_error')),
                (string) (__('legacy/functions.std_permission_denied')),
            );
        }

        self::abort(
            (string) (__('legacy/functions.std_sorry')),
            (string) (__('legacy/functions.std_permission_denied_only'))
                .UserClass::name($allowMinimumClass, false, true, true)
                .(string) (__('legacy/functions.std_or_above_can_view'))
                .view('components.permission-faq-note', ['siteName' => SiteConfig::current()->basic->siteName()])->render(),
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
        bool $log = true,
    ): bool {
        if (is_array($value)) {
            foreach ($value as $val) {
                self::assertId($val, $stdhead, $stdfoot, $log);
            }

            return true;
        }

        if (Validators::isId($value)) {
            return true;
        }

        $CURUSER = CurrentUser::instance()->get() ?? [];

        $msg = 'Invalid ID Attempt: Username: '.(CurrentUser::instance()->username())
            .' - UserID: '.(CurrentUser::instance()->value('id', ''))
            .' - UserIP : '.(Network::clientIp());

        if ($log && \function_exists('write_log')) {
            Log::writeWithContext($msg, 'mod');
        }
        if (\function_exists('do_log')) {
            Logger::writeWithContext($msg, 'error');
        }

        if ($stdhead) {
            self::abort(
                (string) (__('legacy/functions.std_error')),
                (string) (__('legacy/functions.std_invalid_id')),
            );
        }

        $errorHtml = view('partials.int-error', [
            'heading' => (string) (__('legacy/functions.std_error')),
            'text' => (string) (__('legacy/functions.std_invalid_id')),
        ])->render();

        $renderer = self::pageRenderer();
        $html = ($stdfoot ? (string) $renderer->headerHtml() : '')
            .$errorHtml
            .($stdfoot ? (string) $renderer->footerHtml() : '');

        throw new HttpResponseException(new Response($html));
    }

    /**
     * Legacy upload permission gate.
     *
     * Returns `true` if the current user may upload, `false` if not.
     * Aborts with an error page if the approval-deny limit is reached.
     */
    public static function canUpload(string $where = 'torrents'): bool
    {
        $CURUSER = CurrentUser::instance()->get() ?? [];

        if (! (CurrentUser::instance()->value('uploadpos', true))) {
            return false;
        }

        $uploadDenyApprovalDenyCount = (int) SiteConfig::current()->main->uploadDenyApprovalDenyCount();
        $approvalDenyCount = app(TorrentModerationRepository::class)->getApprovalDenyCount((int) (CurrentUser::instance()->id()));

        if ($uploadDenyApprovalDenyCount > 0 && $approvalDenyCount >= $uploadDenyApprovalDenyCount) {
            self::abort(
                (string) (__('legacy/functions.std_sorry')),
                \sprintf((string) (__('legacy/functions.approval_deny_reach_upper_limit')), $uploadDenyApprovalDenyCount),
                false,
            );
        }

        if ($where === 'torrents') {
            $offerSkipApprovedCount = (int) SiteConfig::current()->main->offerSkipApprovedCount();
            if ((CurrentUser::instance()->value('offer_allowed_count', 0)) >= $offerSkipApprovedCount) {
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
     * Emit a 404 Not Found response and exit.
     *
     * Mirrors `httperr()`.
     */
    public static function notFound(): void
    {
        throw new HttpResponseException(new Response(view('support._not-found')->render(), 404));
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

        // T-11: Use LegacyHeaderBag instead of SAPI headers_sent() to avoid
        // cross-request state leakage under Octane. If output has already
        // been emitted (ob_get_level() > 0 with content), use a JS redirect.
        if (ob_get_level() > 0 && (string) ob_get_status()['name'] !== '') {
            $nonce = (string) request()->attributes->get('csp_nonce', '');
            $nonceAttr = $nonce !== '' ? ' nonce="'.htmlspecialchars($nonce, ENT_QUOTES).'"' : '';
            throw new HttpResponseException(new Response(trim(view('support._js-redirect', [
                'nonceAttr' => SafeHtml::fromTrustedHtml($nonceAttr),
                'url' => $url,
            ])->render())));
        }

        throw new HttpResponseException(new RedirectResponse($url, 302));
    }

    private static function pageRenderer(): PageRenderer
    {
        return app(PageRenderer::class);
    }
}
