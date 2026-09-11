<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Http\LegacyScriptContext;
use App\Http\LegacyUrlRewriter;
use App\Support\Bootstrap;
use App\Support\CurrentUser;
use App\Support\LegacyBootstrap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boot the legacy request context for every HTTP request.
 *
 * This middleware replaces the manual `public/index.php` pre-processing:
 * it rewrites legacy query parameters to Laravel paths, sets the legacy
 * SCRIPT_NAME/PATH_INFO server values, boots cache/Eloquent/settings/language,
 * loads per-page language files, runs the legacy parked() guard and schedules
 * the periodic autoclean task. Because it runs inside the Laravel middleware
 * pipeline it is Octane-compatible and runs once per worker request.
 */
final class LegacyRequestMiddleware
{
    public function __construct(
        private readonly LegacyUrlRewriter $urlRewriter,
        private readonly LegacyScriptContext $scriptContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        defined('IN_NEXUS') || define('IN_NEXUS', false);

        $request = $this->urlRewriter->rewrite($request);

        // Make the rewritten request available to the container and URL generator
        // before the legacy bootstrap runs (Nexus/SupportContext read from it).
        $this->bindRequest($request);

        // Reset the per-request CurrentUser cache so it re-reads from Auth
        // on each request (Octane/test compatibility).
        app(CurrentUser::class)->reset();

        $rootpath = base_path().'/';
        LegacyBootstrap::boot($request, $rootpath);

        $script = $this->detectScript($request);

        $this->scriptContext->boot($script, $rootpath);

        app(PageLayoutRepositoryInterface::class)->prepareAccess();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(PageLayoutRepositoryInterface::class)->flushAccess();

        if ($this->detectScript($request) === 'index') {
            Bootstrap::autoClean((bool) false);
        }
    }

    private function bindRequest(Request $request): void
    {
        app()->instance('request', $request);

        if (app()->bound('url')) {
            app('url')->setRequest($request);
        }
    }

    private function detectScript(Request $request): string
    {
        $scriptName = (string) $request->server->get('SCRIPT_NAME', '');
        $script = preg_replace('/\.php$/', '', basename($scriptName)) ?? '';
        $script = preg_replace('/[^a-zA-Z0-9_-]/', '', $script) ?? '';

        return $script === '' ? 'index' : $script;
    }
}
