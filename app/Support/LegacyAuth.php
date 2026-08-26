<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\AuthRepository;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Temporary Phase 5 migration shim for legacy authentication / captcha helpers.
 *
 * All methods now receive a {@see LegacyAuthContext} instead of reading
 * `$GLOBALS` or super-globals directly. The procedural wrappers in
 * `include/functions.php` still assemble the context from the legacy global
 * state, which keeps `App\Support` free of `$_GET`/`$_POST`/`$_COOKIE`/`$_SERVER`
 * and `$GLOBALS`.
 */
final class LegacyAuth
{
    /**
     * Legacy pre-login IP ban check.
     */
    public static function failedLoginsCheck(string $type, LegacyAuthContext $context): void
    {
        $lang = $context->lang;
        $maxAttempts = $context->maxLoginAttempts;
        $ip = $context->ip;

        $total = app(AuthRepository::class)->getLoginAttemptsSum($ip);

        if ($total >= $maxAttempts) {
            app(AuthRepository::class)->banLoginAttempts($ip);

            LegacyResponse::abort(
                $type.($lang['std_locked'] ?? '').$maxAttempts.($lang['std_attempts_reached'] ?? ''),
                (string) ($lang['std_your_ip_banned'] ?? ''),
                true,
                true,
            );
        }
    }

    /**
     * Record a failed login attempt and abort with the login-specific message.
     */
    public static function failedLogins(string $type, bool $recover, bool $head, LegacyAuthContext $context): void
    {
        self::recordFailedLogin($type, $recover, $head, 'std_failed', $context);
    }

    /**
     * Record a failed login/recover attempt and abort with the recover message.
     */
    public static function loginFailedLogins(string $type, bool $recover, bool $head, LegacyAuthContext $context): void
    {
        self::recordFailedLogin($type, $recover, $head, 'std_recover_failed', $context);
    }

    /**
     * Legacy captcha verification.
     */
    public static function checkCode(
        string $imagehash,
        string $imagestring,
        string $where,
        bool $maxattemptlog,
        bool $head,
        LegacyAuthContext $context,
    ): bool {
        $lang = $context->lang;

        if (! $context->captchaEnabled) {
            return true;
        }

        $manager = Captcha::manager();

        if (! $manager->isEnabled()) {
            return true;
        }

        $payload = [
            'imagehash' => $imagehash,
            'imagestring' => $imagestring,
            'request' => $context->request,
        ];

        $captchaContext = [
            'where' => $where,
            'maxattemptlog' => $maxattemptlog,
            'head' => $head,
            'ip' => $context->ip,
        ];

        try {
            if ($manager->verify($payload, $captchaContext)) {
                return true;
            }
        } catch (CaptchaValidationException $exception) {
            $message = $exception->getMessage();

            $defaultMessage = ($lang['std_invalid_image_code'] ?? '')
                .'<a href="'.\htmlspecialchars($where).'">'
                .($lang['std_here_to_request_new'] ?? '');

            if ($message === '' || $message === 'Invalid captcha response.' || $message === 'Missing captcha parameters.') {
                $message = $defaultMessage;
            }

            if (! $maxattemptlog) {
                LegacyResponse::abort('Error', $message, false);
            } else {
                self::recordFailedLogin($message, true, $head, 'std_failed', $context);
            }
        }

        return false;
    }

    private static function recordFailedLogin(
        string $type,
        bool $recover,
        bool $head,
        string $failedLangKey,
        LegacyAuthContext $context,
    ): void {
        $lang = $context->lang;
        $ip = $context->ip;

        app(AuthRepository::class)->recordFailedLogin($ip, $recover);

        if ($type === 'silent') {
            return;
        }

        if ($type === 'login') {
            LegacyResponse::abort(
                (string) ($lang['std_login_failed'] ?? ''),
                (string) ($lang['std_login_failed_note'] ?? ''),
                false,
                $head,
            );
        } else {
            LegacyResponse::abort(
                (string) ($lang[$failedLangKey] ?? ''),
                $type,
                false,
                $head,
            );
        }
    }

    /**
     * Legacy "already logged in" guard.
     */
    public static function currentUserCheck(LegacyAuthContext $context): void
    {
        $lang = $context->lang;

        if ($context->isLoggedIn()) {
            app(AuthRepository::class)->updateUserLang((int) ($context->user['id'] ?? 0), $context->langId());

            LegacyResponse::abort(
                (string) ($lang['std_permission_denied'] ?? ''),
                (string) ($lang['std_already_logged_in'] ?? ''),
            );
        }
    }

    /**
     * Legacy "account parked" guard.
     */
    public static function parked(LegacyAuthContext $context): void
    {
        $lang = $context->lang;

        if (($context->user['parked'] ?? '') === 'yes') {
            LegacyResponse::abort(
                (string) ($lang['std_access_denied'] ?? ''),
                (string) ($lang['std_your_account_parked'] ?? ''),
            );
        }
    }

    /**
     * Legacy registration/invite system gate.
     */
    public static function registrationCheck(
        string $type,
        bool $maxuserscheck,
        bool $ipcheck,
        LegacyAuthContext $context,
    ): bool {
        $lang = $context->lang;
        $settings = $context->registration;

        if ($type === 'invitesystem') {
            if ($settings['invitesystem'] === 'no') {
                LegacyResponse::abort(
                    (string) ($lang['std_oops'] ?? ''),
                    (string) ($lang['std_invite_system_disabled'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($type === 'normal') {
            if ($settings['registration'] === 'no') {
                LegacyResponse::abort(
                    (string) ($lang['std_sorry'] ?? ''),
                    (string) ($lang['std_open_registration_disabled'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($maxuserscheck) {
            $userCount = app(AuthRepository::class)->countUsers();
            if ($userCount >= $settings['maxusers']) {
                LegacyResponse::abort(
                    (string) ($lang['std_sorry'] ?? ''),
                    (string) ($lang['std_account_limit_reached'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($ipcheck) {
            $ip = $context->ip;
            $ipCount = app(AuthRepository::class)->countUsersByIp($ip);
            if ($ipCount > $settings['maxip']) {
                LegacyResponse::abort(
                    (string) ($lang['std_sorry'] ?? ''),
                    (string) ($lang['std_the_ip'] ?? '').'<b>'.\htmlspecialchars($ip).'</b>'.\sprintf((string) ($lang['std_used_many_times'] ?? ''), Setting::getSiteName()),
                    false,
                    true,
                );
            }
        }

        return true;
    }

    /**
     * Remaining login attempts for the current IP.
     *
     * Mirrors the legacy `remaining()` helper: counts the `attempts`
     * column in `loginattempts`, subtracts from `$maxAttempts`, and
     * returns a small red/green HTML fragment.
     */
    public static function remainingAttemptsFromContext(string $type = 'login'): string
    {
        $context = LegacyAuthContext::fromSupportContext();

        return self::remainingAttempts($type, $context->maxLoginAttempts, $context->ip);
    }

    public static function remainingAttempts(string $type, int $maxAttempts, string $ip): string
    {
        $total = app(AuthRepository::class)->getLoginAttemptsSum($ip);

        $remaining = $maxAttempts - $total;

        return $remaining <= 2
            ? '<font color="red" size="2">['.$remaining.']</font>'
            : '<font color="green" size="2">['.$remaining.']</font>';
    }

    /**
     * Legacy login guard: if no current user, redirect to login.php
     * (with returnto for non-main pages, or just login.php for main
     * pages). For ajax calls, return a JSON `fail()` response. If the
     * user is disabled and the current script is not self-enable, redirect
     * to self-enable.php.
     *
     * Mirrors `loggedinorreturn()`.
     */
    public static function requireLogin(bool $mainPage, LegacyAuthContext $context): void
    {
        if (! $context->isLoggedIn()) {
            if ($context->script === 'ajax') {
                throw new HttpResponseException(new JsonResponse(Api::fail('Not login!', $context->requestBody, $context->request), 401));
            }

            if ($mainPage) {
                LegacyResponse::redirect('login.php');
            } else {
                $returnTo = $context->requestUri !== null && $context->requestUri !== ''
                    ? rawurlencode(basename($context->requestUri))
                    : '';
                LegacyResponse::redirect('login.php?returnto='.$returnTo);
            }
        }

        if (($context->user['enabled'] ?? '') !== 'yes' && $context->script !== 'self-enable') {
            LegacyResponse::redirect('self-enable.php');
        }
    }

    /**
     * Look up a user id by username (case-insensitive). Aborts on failure.
     */
    public static function userIdFromName(string $username, LegacyAuthContext $context): int
    {
        $lang = $context->lang;

        $id = app(AuthRepository::class)->getUserIdByUsername($username);

        if ($id === null) {
            LegacyResponse::abort(
                (string) ($lang['std_error'] ?? ''),
                (string) ($lang['std_no_user_named'] ?? '')."'".$username."'",
            );
        }

        return (int) $id;
    }

    /**
     * Bootstrap the current user from the legacy auth cookie.
     *
     * Mirrors `userlogin()`: checks the IP ban list, reads the user from
     * the cookie, generates a missing passkey, and returns the user row.
     * The caller is responsible for populating app(CurrentUser::class)->set() so the
     * rest of the legacy page keeps working.
     *
     * @return array<string, mixed>|null
     */
    public static function loginFromCookie(LegacyAuthContext $context): ?array
    {
        $lang = $context->lang;
        $cache = $context->cache;

        $ip = $context->ip;
        $nip = ip2long($ip);

        if ($nip && app(AuthRepository::class)->isIpBanned($nip)) {
            $html = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.($lang['text_unauthorized_ip'] ?? '')."</body></html>\n";
            throw new HttpResponseException(new Response($html, 403));
        }

        $row = AuthCookie::userFromCookie($context->cookies, true);
        if (empty($row)) {
            return null;
        }
        if ($row instanceof User) {
            $row = $row->toArray();
        }

        if (! $row['passkey']) {
            $passkey = md5($row['username'].date('Y-m-d H:i:s').$row['passhash']);
            app(AuthRepository::class)->updateUserPasskey((int) $row['id'], $passkey);
        }

        $row['old_ip'] = $row['ip'];
        $row['ip'] = $ip;
        $row['seedbonus'] = floatval($row['seedbonus']);

        if (isset($context->queryParams['clearcache']) && $context->queryParams['clearcache'] && (int) ($row['class'] ?? 0) >= $context->moderatorClass && $cache !== null && method_exists($cache, 'setClearCache')) {
            $cache->setClearCache(1);
        }

        return $row;
    }

    /**
     * Bootstrap the current user from the auth cookie and populate
     * {@see SupportContext}. Replaces the legacy `userlogin()` helper.
     */
    public static function loginFromContext(): bool
    {
        $context = LegacyAuthContext::fromSupportContext();
        $user = self::loginFromCookie($context);

        if ($user !== null) {
            SupportContext::setGlobal('oldip', $user['old_ip'] ?? $user['ip'] ?? '');
            SupportContext::setGlobal('CURUSER', $user);
            app(CurrentUser::class)->set($user);

            return true;
        }

        SupportContext::setGlobal('CURUSER', null);
        app(CurrentUser::class)->set(null);

        return false;
    }

    /**
     * Run the legacy registration/invite system gate using the current context.
     * Replaces the legacy `registration_check()` helper.
     */
    public static function registrationCheckFromContext(
        string $type = 'invitesystem',
        bool $maxuserscheck = true,
        bool $ipcheck = true,
    ): bool {
        return self::registrationCheck($type, $maxuserscheck, $ipcheck, LegacyAuthContext::fromSupportContext());
    }

    /**
     * Run the legacy "account parked" guard using the current context.
     * Replaces the legacy `parked()` helper.
     */
    public static function parkedFromContext(): void
    {
        self::parked(LegacyAuthContext::fromSupportContext());
    }

    /**
     * Run the legacy login guard using the current context.
     * Replaces the legacy `loggedinorreturn()` helper.
     */
    public static function requireLoginFromContext(bool $mainPage = false): void
    {
        self::requireLogin($mainPage, LegacyAuthContext::fromSupportContext());
    }
}
