<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\AuthContext;
use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Security\PasskeyGenerator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Legacy guest-access / login-mode helpers extracted from
 * `include/functions.php`.
 *
 * Backs `checkGuestVisit()` and `canDoLogin()`. These methods still
 * perform HTTP side effects (die / header / render) because they are
 * gate functions used during the legacy bootstrap.
 */
final class SiteAccess
{
    /**
     * Check whether the current guest may proceed to the requested page,
     * or whether the configured guest-visit mode (static page, custom
     * content, redirect) should short-circuit the request.
     *
     * Mirrors `checkGuestVisit()`.
     */
    public static function checkGuestVisit(): void
    {
        if (self::loginFromCookie()) {
            return;
        }

        $setting = SiteConfig::current()->security->toArray();
        $guestVisitType = (string) ($setting['guest_visit_type'] ?? '');

        if ($guestVisitType === '' || $guestVisitType === 'normal') {
            return;
        }

        if (in_array(RequestContext::instance()->getScript(), ['login', 'takelogin', 'image']) && self::canDoLogin()) {
            return;
        }

        $valueKey = "guest_visit_value_$guestVisitType";
        if (empty($setting[$valueKey])) {
            Logger::writeWithContext("setting: security.$valueKey empty");
            throw new HttpResponseException(new Response('', 500));
        }

        $guestVisitValue = $setting[$valueKey];

        if ($guestVisitType === 'static_page') {
            $pageFile = ROOT_PATH.'resources/static-pages/'.$guestVisitValue;
            if (! file_exists($pageFile) || ! is_readable($pageFile)) {
                Logger::writeWithContext("pageFile: $pageFile is not exists or readable");
                throw new HttpResponseException(new Response('', 500));
            }
            throw new HttpResponseException(new Response(\file_get_contents($pageFile) ?: '', 200, ['Content-Type' => 'text/html']));
        }

        if ($guestVisitType === 'custom_content') {
            $content = Format::formatComment($guestVisitValue);
            View::render('resources/templates/guest-visit-custom-content', ['content' => $content], false, ROOT_PATH);

            return;
        }

        if ($guestVisitType === 'redirect') {
            throw new HttpResponseException(new RedirectResponse($guestVisitValue, 302));
        }
    }

    /**
     * Determine whether the current login request is allowed under the
     * configured login mode (normal / secret / passkey).
     *
     * Mirrors `canDoLogin()`.
     */
    public static function canDoLogin(): bool
    {
        $setting = SiteConfig::current()->security->toArray();

        if (empty($setting['login_type']) || $setting['login_type'] === 'normal') {
            return true;
        }

        $loginType = $setting['login_type'];

        if ($loginType === 'secret') {
            if (empty(request()->input('secret'))) {
                Logger::writeWithContext('no secret');

                return false;
            }
            $secret = request()->input('secret');
            if ($secret !== $setting['login_secret']) {
                Logger::writeWithContext('invlaid secret: '.$secret);

                return false;
            }
            if ($setting['login_secret_deadline'] < date('Y-m-d H:i:s')) {
                Logger::writeWithContext("secret: {$secret} expires(deadline: {$setting['login_secret_deadline']})");

                return false;
            }

            return true;
        }

        if ($loginType === 'passkey') {
            return false;
        }

        return true;
    }

    /**
     * Bootstrap the current user from the auth cookie and populate the
     * request-scoped CurrentUser. Mirrors the legacy `userlogin()` helper:
     * checks the IP ban list, reads the user from the cookie and generates
     * a missing passkey.
     */
    private static function loginFromCookie(): bool
    {
        $context = AuthContext::current();
        $user = self::resolveCookieUser($context);

        if ($user !== null) {
            CurrentUser::instance()->set($user);

            return true;
        }

        CurrentUser::instance()->set(null);

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveCookieUser(AuthContext $context): ?array
    {
        $cache = $context->cache;

        $ip = $context->ip;
        $nip = ip2long($ip);
        $authRepository = app(AuthRepositoryInterface::class);

        if ($nip && $authRepository->isIpBanned($nip)) {
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
            $authRepository->updateUserPasskey((int) $row['id'], $passkey);
        }

        $row['old_ip'] = $row['ip'];
        $row['ip'] = $ip;
        $row['seedbonus'] = floatval($row['seedbonus']);

        if (isset($context->queryParams['clearcache']) && $context->queryParams['clearcache'] && (int) ($row['class'] ?? 0) >= $context->moderatorClass && $cache !== null) {
            $cache->setBypass(1);
        }

        return $row;
    }
}
