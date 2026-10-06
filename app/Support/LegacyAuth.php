<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Models\Setting;
use App\Models\User;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use App\Support\Security\PasskeyGenerator;
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

            $defaultMessage = view('auth._invalid_image_code', ['where' => $where])->render();

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
        $ip = $context->ip;

        self::authRepository()->recordFailedLogin($ip, $recover);

        if ($type === 'silent') {
            return;
        }

        if ($type === 'login') {
            LegacyResponse::abort(
                (string) (__('legacy/functions.std_login_failed')),
                view('components.login-failed-note')->render(),
                false,
                $head,
            );
        } else {
            LegacyResponse::abort(
                (string) (__('legacy/functions.'.$failedLangKey)),
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

        if ($context->isLoggedIn()) {
            self::authRepository()->updateUserLang((int) ($context->user['id'] ?? 0), $context->langId());

            LegacyResponse::abort(
                (string) (__('legacy/functions.std_permission_denied')),
                (string) (__('legacy/functions.std_already_logged_in')),
            );
        }
    }

    /**
     * Legacy "account parked" guard.
     */
    public static function parked(LegacyAuthContext $context): void
    {

        if (($context->user['parked'] ?? false)) {
            LegacyResponse::abort(
                (string) (__('legacy/functions.std_access_denied')),
                (string) (__('legacy/functions.std_your_account_parked')),
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
        $settings = $context->registration;

        if ($type === 'invitesystem') {
            if ($settings['invitesystem'] === 'no') {
                LegacyResponse::abort(
                    (string) (__('legacy/functions.std_oops')),
                    (string) (__('legacy/functions.std_invite_system_disabled')),
                    false,
                    true,
                );
            }
        }

        if ($type === 'normal') {
            if ($settings['registration'] === 'no') {
                LegacyResponse::abort(
                    (string) (__('legacy/functions.std_sorry')),
                    (string) (__('legacy/functions.std_open_registration_disabled')),
                    false,
                    true,
                );
            }
        }

        if ($maxuserscheck) {
            $userCount = self::authRepository()->countUsers();
            if ($userCount >= $settings['maxusers']) {
                LegacyResponse::abort(
                    (string) (__('legacy/functions.std_sorry')),
                    (string) (__('legacy/functions.std_account_limit_reached')),
                    false,
                    true,
                );
            }
        }

        if ($ipcheck) {
            $ip = $context->ip;
            $ipCount = self::authRepository()->countUsersByIp($ip);
            if ($ipCount > $settings['maxip']) {
                LegacyResponse::abort(
                    (string) (__('legacy/functions.std_sorry')),
                    view('auth._ip_used_many_times', ['ip' => $ip, 'siteName' => Setting::getSiteName()])->render(),
                    false,
                    true,
                );
            }
        }

        return true;
    }

    /**
     * Legacy login guard: if no current user, redirect to /login
     * (with returnto for non-main pages, or just /login for main
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
                LegacyResponse::redirect('/login');
            } else {
                $returnTo = $context->requestUri !== null && $context->requestUri !== ''
                    ? rawurlencode(basename($context->requestUri))
                    : '';
                LegacyResponse::redirect('/login?returnto='.$returnTo);
            }
        }

        if (! ($context->user['enabled'] ?? false) && $context->script !== 'self-enable') {
            LegacyResponse::redirect('/web/self-enable');
        }
    }

    /**
     * Look up a user id by username (case-insensitive). Aborts on failure.
     */
    public static function userIdFromName(string $username, LegacyAuthContext $context): int
    {

        $id = self::authRepository()->getUserIdByUsername($username);

        if ($id === null) {
            LegacyResponse::abort(
                (string) (__('legacy/functions.std_error')),
                (string) (__('legacy/functions.std_no_user_named'))."'".$username."'",
            );
        }

        return (int) $id;
    }

    /**
     * Bootstrap the current user from the legacy auth cookie.
     *
     * Mirrors `userlogin()`: checks the IP ban list, reads the user from
     * the cookie, generates a missing passkey, and returns the user row.
     * The caller is responsible for populating CurrentUser::instance()->set() so the
     * rest of the legacy page keeps working.
     *
     * @return array<string, mixed>|null
     */
    public static function loginFromCookie(LegacyAuthContext $context): ?array
    {
        $cache = $context->cache;

        $ip = $context->ip;
        $nip = ip2long($ip);

        if ($nip && self::authRepository()->isIpBanned($nip)) {
            $html = view('errors.unauthorized-ip')->render()."\n";
            throw new HttpResponseException(new Response($html, 403));
        }

        $row = AuthCookie::userFromCookie($context->cookies, true);
        if (empty($row)) {
            return null;
        }
        if ($row instanceof User) {
            $row = $row->toArray();
        }

        if (empty($row['passkey'])) {
            $passkey = app(PasskeyGenerator::class)->generate();
            self::authRepository()->updateUserPasskey((int) $row['id'], $passkey);
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
            Globals::instance()->set('oldip', $user['old_ip'] ?? $user['ip'] ?? '');
            Globals::instance()->set('CURUSER', $user);
            CurrentUser::instance()->set($user);

            return true;
        }

        Globals::instance()->set('CURUSER', null);
        CurrentUser::instance()->set(null);

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

    private static function authRepository(): AuthRepositoryInterface
    {
        return app(AuthRepositoryInterface::class);
    }
}
