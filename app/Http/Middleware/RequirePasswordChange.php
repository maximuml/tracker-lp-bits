<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan 2.1: force users flagged with `must_change_password` through the
 * password-change screen before they can use the site.
 *
 * Runs in the global stack (the legacy auth route groups do not use the
 * `web` group), so it must self-exempt everything that must keep working
 * for a flagged user: the usercp page/action itself, auth endpoints, and
 * all non-interactive surfaces (API, tracker, assets, ops endpoints).
 */
final class RequirePasswordChange
{
    /**
     * Rewritten-path patterns a flagged user may still reach.
     *
     * @var list<string>
     */
    private const EXEMPT_PATHS = [
        'usercp*',          // password change lives at /usercp(.php)?action=security
        'logout*',
        'login*', 'takelogin*', 'signup*', 'takesignup*',
        'recover*', 'confirm*', 'verify*', 'error',
        'announce*', 'scrape*',              // tracker protocol
        'api/*',                             // token-authenticated API
        'health*', 'metrics', 'nexus',       // ops
        'filament/*', 'nexusphp/*', 'livewire/*', 'horizon*',
        'build/*', 'storage/*', 'vendor/*', 'assets/*', 'images/*', 'pic/*',
        'js/*', 'css/*', 'fonts/*', 'favicon.ico', 'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Legacy pages authenticate via the `nexus-web` cookie guard; the
        // session `web` guard covers modern/session flows.
        $user = Auth::guard('nexus-web')->user() ?? Auth::user();

        if ($user instanceof User
            && $user->must_change_password
            && ! $request->is(self::EXEMPT_PATHS)
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ret' => 1,
                    'msg' => 'password_change_required',
                ], 403);
            }

            return redirect('/usercp.php?action=security');
        }

        return $next($request);
    }
}
