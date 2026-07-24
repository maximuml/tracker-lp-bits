<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Nexus\Database\NexusDB;

/**
 * Temporary Phase 5 migration shim for legacy authentication / captcha helpers.
 *
 * Collects the login-attempt tracking and image-code verification helpers
 * from `include/functions.php`. They will be dissolved into proper Form
 * Requests / services once the legacy bootstrap is gone.
 */
final class LegacyAuth
{
    /**
     * Legacy pre-login IP ban check.
     */
    public static function failedLoginsCheck(string $type = 'Login'): void
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $maxloginattempts = $GLOBALS['maxloginattempts'] ?? 0;
        $ip = \function_exists('getip') ? \getip() : '';

        $total = (int) NexusDB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

        if ($total >= $maxloginattempts) {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['banned' => 'yes']);

            LegacyResponse::abort(
                $type.($lang_functions['std_locked'] ?? '').$maxloginattempts.($lang_functions['std_attempts_reached'] ?? ''),
                (string) ($lang_functions['std_your_ip_banned'] ?? ''),
                true,
                true,
            );
        }
    }

    /**
     * Record a failed login attempt and abort with the login-specific message.
     */
    public static function failedLogins(string $type = 'login', bool $recover = false, bool $head = true): void
    {
        self::recordFailedLogin($type, $recover, $head, 'std_failed');
    }

    /**
     * Record a failed login/recover attempt and abort with the recover message.
     */
    public static function loginFailedLogins(string $type = 'login', bool $recover = false, bool $head = true): void
    {
        self::recordFailedLogin($type, $recover, $head, 'std_recover_failed');
    }

    /**
     * Legacy captcha verification.
     */
    public static function checkCode(
        string $imagehash,
        string $imagestring,
        string $where = 'signup.php',
        bool $maxattemptlog = false,
        bool $head = true,
    ): bool {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $iv = $GLOBALS['iv'] ?? '';

        if ($iv !== 'yes') {
            return true;
        }

        $manager = \captcha_manager();

        if (! $manager->isEnabled()) {
            return true;
        }

        $payload = [
            'imagehash' => $imagehash,
            'imagestring' => $imagestring,
            'request' => array_merge((array) $_POST, (array) $_GET),
        ];

        $context = [
            'where' => $where,
            'maxattemptlog' => $maxattemptlog,
            'head' => $head,
            'ip' => \function_exists('getip') ? \getip() : '',
        ];

        try {
            if ($manager->verify($payload, $context)) {
                return true;
            }
        } catch (CaptchaValidationException $exception) {
            $message = $exception->getMessage();

            $defaultMessage = ($lang_functions['std_invalid_image_code'] ?? '')
                .'<a href="'.\htmlspecialchars($where).'">'
                .($lang_functions['std_here_to_request_new'] ?? '');

            if ($message === '' || $message === 'Invalid captcha response.' || $message === 'Missing captcha parameters.') {
                $message = $defaultMessage;
            }

            if (! $maxattemptlog) {
                LegacyResponse::abort('Error', $message, false);
            } else {
                self::failedLogins($message, true, $head);
            }
        }

        return false;
    }

    private static function recordFailedLogin(string $type, bool $recover, bool $head, string $failedLangKey): void
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $ip = \function_exists('getip') ? \getip() : '';

        $count = (int) NexusDB::table('loginattempts')
            ->where('ip', $ip)
            ->count();

        if ($count == 0) {
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => 1,
            ]);
        } else {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['attempts' => NexusDB::raw('attempts + 1')]);
        }

        if ($recover) {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['type' => 'recover']);
        }

        if ($type === 'silent') {
            return;
        }

        if ($type === 'login') {
            LegacyResponse::abort(
                (string) ($lang_functions['std_login_failed'] ?? ''),
                (string) ($lang_functions['std_login_failed_note'] ?? ''),
                false,
                $head,
            );
        } else {
            LegacyResponse::abort(
                (string) ($lang_functions[$failedLangKey] ?? ''),
                $type,
                false,
                $head,
            );
        }
    }

    /**
     * Legacy "already logged in" guard.
     */
    public static function currentUserCheck(): void
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $CURUSER = $GLOBALS['CURUSER'] ?? [];

        if ($CURUSER) {
            NexusDB::table('users')
                ->where('id', $CURUSER['id'] ?? 0)
                ->update(['lang' => \function_exists('get_langid_from_langcookie') ? \get_langid_from_langcookie() : '']);

            LegacyResponse::abort(
                (string) ($lang_functions['std_permission_denied'] ?? ''),
                (string) ($lang_functions['std_already_logged_in'] ?? ''),
            );
        }
    }

    /**
     * Legacy "account parked" guard.
     */
    public static function parked(): void
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $CURUSER = $GLOBALS['CURUSER'] ?? [];

        if (($CURUSER['parked'] ?? '') === 'yes') {
            LegacyResponse::abort(
                (string) ($lang_functions['std_access_denied'] ?? ''),
                (string) ($lang_functions['std_your_account_parked'] ?? ''),
            );
        }
    }

    /**
     * Legacy registration/invite system gate.
     */
    public static function registrationCheck(
        string $type = 'invitesystem',
        bool $maxuserscheck = true,
        bool $ipcheck = true,
    ): bool {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $invitesystem = $GLOBALS['invitesystem'] ?? '';
        $registration = $GLOBALS['registration'] ?? '';
        $maxusers = $GLOBALS['maxusers'] ?? 0;
        $maxip = $GLOBALS['maxip'] ?? 0;

        if ($type === 'invitesystem') {
            if ($invitesystem === 'no') {
                LegacyResponse::abort(
                    (string) ($lang_functions['std_oops'] ?? ''),
                    (string) ($lang_functions['std_invite_system_disabled'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($type === 'normal') {
            if ($registration === 'no') {
                LegacyResponse::abort(
                    (string) ($lang_functions['std_sorry'] ?? ''),
                    (string) ($lang_functions['std_open_registration_disabled'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($maxuserscheck) {
            $userCount = (int) NexusDB::table('users')->count();
            if ($userCount >= $maxusers) {
                LegacyResponse::abort(
                    (string) ($lang_functions['std_sorry'] ?? ''),
                    (string) ($lang_functions['std_account_limit_reached'] ?? ''),
                    false,
                    true,
                );
            }
        }

        if ($ipcheck) {
            $ip = \function_exists('getip') ? \getip() : '';
            $ipCount = (int) NexusDB::table('users')->where('ip', $ip)->count();
            if ($ipCount > $maxip) {
                LegacyResponse::abort(
                    (string) ($lang_functions['std_sorry'] ?? ''),
                    (string) ($lang_functions['std_the_ip'] ?? '').'<b>'.\htmlspecialchars($ip).'</b>'.\sprintf((string) ($lang_functions['std_used_many_times'] ?? ''), Setting::getSiteName()),
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
    public static function remainingAttempts(string $type, int $maxAttempts, string $ip): string
    {
        $total = (int) NexusDB::table('loginattempts')
            ->where('ip', $ip)
            ->sum('attempts');

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
    public static function requireLogin(bool $mainPage = false): void
    {
        $CURUSER = $GLOBALS['CURUSER'] ?? null;

        if (! $CURUSER) {
            if (nexus()->getScript() === 'ajax') {
                exit(fail('Not login!', $_POST));
            }

            if ($mainPage) {
                nexus_redirect('login.php');
            } else {
                nexus_redirect('login.php?returnto=' . rawurlencode(basename($_SERVER['REQUEST_URI'] ?? '')));
            }
            exit;
        }

        if (($CURUSER['enabled'] ?? '') !== 'yes' && nexus()->getScript() !== 'self-enable') {
            nexus_redirect('self-enable.php');
        }
    }

    /**
     * Look up a user id by username (case-insensitive). Aborts on failure.
     */
    public static function userIdFromName(string $username): int
    {
        $lang_functions = $GLOBALS['lang_functions'] ?? [];

        $id = NexusDB::table('users')
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
            ->value('id');

        if ($id === null) {
            LegacyResponse::abort(
                (string) ($lang_functions['std_error'] ?? ''),
                (string) ($lang_functions['std_no_user_named'] ?? '')."'".$username."'",
            );
        }

        return (int) $id;
    }
}
