<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Config\SiteConfig;
use App\Support\Environment;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = 'tg-webhook';

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Only webhooks and endpoints that cannot use CSRF tokens
     * (called by external services, or legacy AJAX endpoints using
     * raw XMLHttpRequest without csrf.js). Form-based routes and
     * jQuery AJAX calls are protected via csrf.js auto-injection.
     *
     * @var array<int, string>
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX.'/*',
        'getusertorrentlistajax',
        'setlist_lookup',
    ];

    /**
     * Determine if the request has a URI that should be excluded from CSRF.
     *
     * Excludes the dynamic passkey-login URI (configured via the
     * `login_secret` setting) so that external tools can POST to it
     * without a CSRF token.
     */
    protected function inExceptArray($request): bool
    {
        if (parent::inExceptArray($request)) {
            return true;
        }

        if (! Environment::isConsole()) {
            $passkeyLoginUri = SiteConfig::current()->security->loginSecret();
            if (! empty($passkeyLoginUri) && $request->is($passkeyLoginUri)) {
                return true;
            }
        }

        return false;
    }
}
