<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Config\SiteConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runtime gate for POST /auth/passkey (passkey login v2).
 *
 * The route is registered unconditionally so `php artisan route:cache`
 * keeps it; whether the feature accepts requests is decided here per
 * request instead — settings live in the database and may change after
 * routes are cached. A disabled feature or an expired enrollment
 * deadline answers 404, the same as if the route did not exist.
 */
final class PasskeyV2Enabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $security = SiteConfig::current()->security;
        $deadline = $security->loginSecretDeadline();

        if (
            ! $security->passkeyLoginV2Enabled()
            || $deadline === null
            || $deadline <= now()->toDateTimeString()
        ) {
            abort(404);
        }

        return $next($request);
    }
}
