<?php

namespace App\Http\Middleware;

use App\Support\LegacyBootstrap;
use App\Support\SupportContext;
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
    /** @var array<string, string|array<int, string>> */
    private const EXTRA_LANG_FILES = [
        'search' => ['torrents.php'],
        'shoutbox_history' => ['shoutbox.php'],
        'take-increment-bulk' => ['increment-bulk.php'],
        'upload' => ['edit.php'],
    ];

    /** @var array<int, string> */
    private const PARKED_SCRIPTS = [
        'viewsnatches', 'users', 'forums', 'report', 'cheaterbox', 'upload',
        'offers', 'comment', 'userdetails', 'checkuser', 'invite', 'bitbucket-upload',
        'mybonus', 'userhistory', 'moresmilies', 'torrents', 'getattachment',
        'sendmessage', 'reports', 'self-enable', 'friends', 'settings', 'topten', 'attendance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request = $this->prepareRequest($request);

        // Make the rewritten request available to the container and URL generator
        // before the legacy bootstrap runs (Nexus/SupportContext read from it).
        $this->bindRequest($request);

        $rootpath = base_path() . '/';
        LegacyBootstrap::boot($request, $rootpath);

        $script = $this->detectScript($request);

        $this->loadScriptLanguage($script, $rootpath);

        if (in_array($script, self::PARKED_SCRIPTS, true)) {
            \App\Support\LegacyAuth::parkedFromContext();
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->detectScript($request) === 'index') {
            \autoclean();
        }
    }

    private function prepareRequest(Request $request): Request
    {
        $server = $request->server->all();
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $parsedUrl = parse_url($requestUri);
        if ($parsedUrl === false) {
            $parsedUrl = ['path' => '/', 'query' => ''];
        }
        $requestPath = $parsedUrl['path'] ?? '/';

        $scriptFilename = (string) ($server['SCRIPT_FILENAME'] ?? public_path('index.php'));
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '');

        $executedScript = basename($scriptFilename);
        if ($executedScript === '' || $executedScript === 'index.php') {
            $executedScript = basename($scriptName);
        }

        $isWrapper = ($executedScript !== '' && $executedScript !== 'index.php');

        $page = '';
        $pathInfo = '';

        // Laravel API routes already use the correct path; do not rewrite them
        // as legacy /script.php/pathinfo requests.
        if (str_starts_with($requestPath, '/api/') || $requestPath === '/api') {
            $server['REQUEST_URI'] = $requestUri;
            $server['REQUEST_METHOD'] = $request->getMethod();
            $server['SCRIPT_NAME'] = '/index.php';
            $server['SCRIPT_FILENAME'] = public_path('index.php');
            if (isset($server['PATH_INFO'])) {
                unset($server['PATH_INFO']);
            }
            $post = $request->getMethod() === 'POST' ? $request->request->all() : [];

            return $request->duplicate($request->query->all(), $post, $request->attributes->all(), $request->cookies->all(), $request->files->all(), $server);
        }

        if ($isWrapper) {
            $page = preg_replace('/\.php$/', '', $executedScript) ?? '';
            $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?? '';

            if ($page !== '' && preg_match('#^/' . preg_quote($executedScript, '#') . '(/.*)$#', $requestPath, $matches)) {
                $pathInfo = $matches[1];
            }
        }

        if ($page === '') {
            if ($requestPath === '/' || $requestPath === '') {
                $routePath = '/';
                $pathInfo = '';
            } elseif (preg_match('#^/([a-zA-Z0-9_-]+)(?:\.php)?(/.*)?$#', $requestPath, $matches)) {
                $routePath = '/' . $matches[1];
                $pathInfo = $matches[2] ?? '';
            } else {
                $routePath = $requestPath;
                $pathInfo = (string) ($server['PATH_INFO'] ?? '');
            }
        } else {
            $routePath = '/' . $page;
            if ($pathInfo !== '') {
                $server['PATH_INFO'] = $pathInfo;
            } elseif (isset($server['PATH_INFO'])) {
                unset($server['PATH_INFO']);
            }
        }

        if ($isWrapper && $page !== '') {
            $script = $page;
        } else {
            $segments = explode('/', trim($routePath, '/'));
            $script = $segments[0] ?? '';
            $script = preg_replace('/[^a-zA-Z0-9_-]/', '', $script) ?? '';
            if ($script === '') {
                $script = 'index';
            }
        }

        $method = $request->getMethod();
        $query = $request->query->all();

        if ($script === 'details' || $script === 'torrent') {
            if (isset($query['id'])) {
                $routePath = '/details/' . (int) $query['id'];
                unset($query['id']);
            } elseif ($pathInfo !== '') {
                $routePath = '/details' . $pathInfo;
            }
        } elseif ($script === 'comment') {
            $commentAction = (string) ($query['action'] ?? '');
            $commentId = (int) ($query['cid'] ?? 0);
            if (in_array($commentAction, ['edit', 'delete', 'vieworiginal'], true)) {
                unset($query['action'], $query['cid']);
                $routePath = '/comment/' . $commentId . '/' . $commentAction;
            } elseif ($commentAction === 'add' && $method === 'GET') {
                unset($query['action']);
                $routePath = '/comment/add';
            } else {
                unset($query['action']);
                $routePath = '/comment';
            }
        } elseif ($script === 'takelogin') {
            $routePath = '/login';
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $uri = $routePath . ($queryString !== '' ? '?' . $queryString : '');

        $server['REQUEST_URI'] = $uri;
        $server['REQUEST_METHOD'] = $method;
        $server['SCRIPT_NAME'] = '/' . $script . '.php';
        $server['SCRIPT_FILENAME'] = public_path($script . '.php');
        $server['PHP_SELF'] = '/' . $script . '.php' . $pathInfo;

        if ($pathInfo !== '') {
            $server['PATH_INFO'] = $pathInfo;
        } elseif (isset($server['PATH_INFO'])) {
            unset($server['PATH_INFO']);
        }

        $post = $method === 'POST' ? $request->request->all() : [];

        return $request->duplicate($query, $post, $request->attributes->all(), $request->cookies->all(), $request->files->all(), $server);
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

    private function loadScriptLanguage(string $script, string $rootpath): void
    {
        $scriptLangFiles = array_unique(array_merge(
            [$script . '.php'],
            self::EXTRA_LANG_FILES[$script] ?? []
        ));

        foreach ($scriptLangFiles as $scriptLangFile) {
            $langPath = $rootpath . get_langfile_path($scriptLangFile);
            if (! is_file($langPath)) {
                continue;
            }

            $SITENAME = SupportContext::getGlobal('SITENAME');
            $SITEEMAIL = SupportContext::getGlobal('SITEEMAIL');
            $REPORTMAIL = SupportContext::getGlobal('REPORTMAIL');
            $BASEURL = SupportContext::getGlobal('BASEURL');
            $before = get_defined_vars();
            require_once $langPath;
            foreach (array_diff_key(get_defined_vars(), $before) as $langKey => $langValue) {
                if (in_array($langKey, ['before', 'path', 'langPath', 'scriptLangFiles', 'rootpath', 'scriptLangFile'], true)) {
                    continue;
                }
                SupportContext::setGlobal($langKey, $langValue);
            }
        }
    }
}
