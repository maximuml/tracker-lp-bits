<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Http\LegacyScriptContext;
use App\Http\LegacyUrlRewriter;
use App\Support\AssetAppender;
use App\Support\Bootstrap;
use App\Support\Cache\NexusCache;
use App\Support\Config;
use App\Support\CurrentUser;
use App\Support\Locale;
use App\Support\NexusContext;
use App\Support\PageState;
use App\Support\RequestContext;
use App\Support\RequestValues;
use App\Support\RuntimeContext;
use App\Support\SiteAccess;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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
        private readonly CurrentUser $currentUser,
        private readonly PageLayoutRepositoryInterface $pageLayoutRepository,
        private readonly Application $app,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // ADR 0017: every request through the HTTP kernel is a legacy-context
        // request (public/index.php semantics); tracker endpoints are marked
        // per request so the flag stays correct under Octane workers.
        $runtime = $this->app->make(RuntimeContext::class);
        $runtime->markLegacy();
        $requestUri = (string) $request->server->get('REQUEST_URI', '');
        if (preg_match('#^/(?:announce|scrape)(?:\.php)?(?:/|$|\?)#', $requestUri) === 1) {
            $runtime->markTracker();
        }

        $request = $this->urlRewriter->rewrite($request);

        // Make the rewritten request available to the container and URL generator
        // before the legacy bootstrap runs (Nexus/SupportContext read from it).
        $this->bindRequest($request);

        // Reset the per-request CurrentUser cache so it re-reads from Auth
        // on each request (Octane/test compatibility).
        $this->currentUser->reset();

        $rootpath = base_path().'/';
        $this->bootLegacyContext($request);

        $script = $this->detectScript($request);

        $this->scriptContext->boot($script, $rootpath);

        $this->pageLayoutRepository->prepareAccess();

        return $next($request);
    }

    /**
     * Legacy bootstrap steps, inlined from the removed LegacyBootstrap:
     * capture the request, cache warm-up, Sanctum token model, timezone,
     * per-page language folder and the guest-visit gate.
     */
    private function bootLegacyContext(Request $request): void
    {
        NexusContext::reset();
        NexusContext::instance()->setFromRequest($request);

        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 0);

        if (defined('RUNNING_IN_OCTANE') && RUNNING_IN_OCTANE) {
            // ResetNexus listener already flushed state; just re-boot
            // the instance with fresh request-scoped data.
            RequestContext::boot();
        } else {
            RequestContext::flush();
            AssetAppender::flush();
            RequestContext::boot();
        }

        // NexusCache is registered as a singleton in
        // AppServiceProvider::register() — resolving it triggers the
        // connection + language folder setup.
        NexusCache::instance();

        if (class_exists(Sanctum::class)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        }

        ini_set('date.timezone', Config::get('nexus.timezone', null));

        $script = RequestContext::instance()->getScript();
        if (! in_array($script, ['announce', 'scrape'], true)) {
            // Legacy per-page language arrays resolve through Laravel's
            // translator (resources/lang/en/*.php). The language folder
            // cookie is still read by Locale::currentFolder() and a few
            // repositories.
            PageState::instance()->setLangDir(Locale::folderFromCookie(RequestValues::cookieValue('c_lang_folder')));
        }

        if (! in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            defined('TIMENOW') || define('TIMENOW', time());
            SiteAccess::checkGuestVisit();
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->pageLayoutRepository->flushAccess();

        if ($this->detectScript($request) === 'index') {
            Bootstrap::autoClean((bool) false);
        }
    }

    private function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);

        if ($this->app->bound('url')) {
            $this->app->make('url')->setRequest($request);
        }
    }

    private function detectScript(Request $request): string
    {
        $scriptName = (string) $request->server->get('SCRIPT_NAME', '');
        $script = preg_replace('/\.php$/', '', basename($scriptName)) ?? '';
        $script = preg_replace('/[^a-zA-Z0-9_-]/', '', $script) ?? '';
        if ($script === 'index' || $script === '') {
            $pageScript = $request->server->get('LEGACY_PAGE_SCRIPT');
            if (is_string($pageScript) && $pageScript !== '') {
                return (string) (preg_replace('/[^a-zA-Z0-9_-]/', '', $pageScript) ?? 'index');
            }
        }

        return $script === '' ? 'index' : $script;
    }
}
