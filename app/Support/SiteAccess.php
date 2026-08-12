<?php

namespace App\Support;

use Nexus\Database\NexusDB;

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
        if (\userlogin()) {
            return;
        }

        $setting = \App\Support\Config\SiteConfig::current()->security->toArray();
        $guestVisitType = (string) ($setting['guest_visit_type'] ?? '');

        if ($guestVisitType === '' || $guestVisitType === 'normal') {
            return;
        }

        if (in_array(\nexus()->getScript(), ['login', 'takelogin', 'image']) && self::canDoLogin()) {
            return;
        }

        $valueKey = "guest_visit_value_$guestVisitType";
        if (empty($setting[$valueKey])) {
            Logger::writeWithContext("setting: security.$valueKey empty");
            die(0);
        }

        $guestVisitValue = $setting[$valueKey];

        if ($guestVisitType === 'static_page') {
            $pageFile = ROOT_PATH . 'resources/static-pages/' . $guestVisitValue;
            if (! file_exists($pageFile) || ! is_readable($pageFile)) {
                Logger::writeWithContext("pageFile: $pageFile is not exists or readable");
                die(0);
            }
            die(\file_get_contents($pageFile) ?: '');
        }

        if ($guestVisitType === 'custom_content') {
            $content = \App\Support\Format::formatComment($guestVisitValue);
            View::render('resources/templates/guest-visit-custom-content', ['content' => $content], false, ROOT_PATH);
        }

        if ($guestVisitType === 'redirect') {
            header('Location: ' . $guestVisitValue);
            die(0);
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
        $setting = \App\Support\Config\SiteConfig::current()->security->toArray();

        if (empty($setting['login_type']) || $setting['login_type'] === 'normal') {
            return true;
        }

        $loginType = $setting['login_type'];

        if ($loginType === 'secret') {
            if (empty(SupportContext::getRequestInput('secret'))) {
                Logger::writeWithContext('no secret');
                return false;
            }
            $secret = SupportContext::getRequestInput('secret');
            if ($secret !== $setting['login_secret']) {
                Logger::writeWithContext('invlaid secret: ' . $secret);
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
}
