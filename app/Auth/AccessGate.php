<?php

declare(strict_types=1);

namespace App\Auth;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Models\Setting;
use App\Support\Api;
use App\Support\LegacyResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Access-gate checks carried over from the legacy auth helpers
 * (`parked()`, `loggedinorreturn()`, `registration_check()`). Each
 * method takes an {@see AuthContext} and aborts via LegacyResponse when
 * the gate denies access.
 */
final class AccessGate
{
    public function __construct(
        private readonly AuthRepositoryInterface $authRepository,
    ) {}

    /**
     * "Account parked" guard.
     */
    public function parked(AuthContext $context): void
    {
        if (($context->user['parked'] ?? false)) {
            LegacyResponse::abort(
                __('functions.std_access_denied'),
                __('functions.std_your_account_parked'),
            );
        }
    }

    /**
     * Login guard: if no current user, redirect to /login
     * (with returnto for non-main pages, or just /login for main
     * pages). For ajax calls, return a JSON `fail()` response. If the
     * user is disabled and the current script is not self-enable, redirect
     * to self-enable.php.
     *
     * Mirrors `loggedinorreturn()`.
     */
    public function requireLogin(bool $mainPage, AuthContext $context): void
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
     * Registration/invite system gate.
     */
    public function registrationCheck(
        string $type,
        bool $maxuserscheck,
        bool $ipcheck,
        AuthContext $context,
    ): bool {
        $settings = $context->registration;

        if ($type === 'invitesystem') {
            if ($settings['invitesystem'] === 'no') {
                LegacyResponse::abort(
                    __('functions.std_oops'),
                    __('functions.std_invite_system_disabled'),
                    false,
                    true,
                );
            }
        }

        if ($type === 'normal') {
            if ($settings['registration'] === 'no') {
                LegacyResponse::abort(
                    __('functions.std_sorry'),
                    __('functions.std_open_registration_disabled'),
                    false,
                    true,
                );
            }
        }

        if ($maxuserscheck) {
            $userCount = $this->authRepository->countUsers();
            if ($userCount >= $settings['maxusers']) {
                LegacyResponse::abort(
                    __('functions.std_sorry'),
                    __('functions.std_account_limit_reached'),
                    false,
                    true,
                );
            }
        }

        if ($ipcheck) {
            $ip = $context->ip;
            $ipCount = $this->authRepository->countUsersByIp($ip);
            if ($ipCount > $settings['maxip']) {
                LegacyResponse::abort(
                    __('functions.std_sorry'),
                    view('auth._ip_used_many_times', ['ip' => $ip, 'siteName' => Setting::getSiteName()])->render(),
                    false,
                    true,
                );
            }
        }

        return true;
    }
}
