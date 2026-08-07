<?php

use App\Support\LegacyBootstrap;
use App\Support\SupportContext;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
defined('IN_NEXUS') || define('IN_NEXUS', true);

$rootpath = dirname(__DIR__) . '/';
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$parsedUrl = parse_url($requestUri);
if ($parsedUrl === false) {
    $parsedUrl = ['path' => '/', 'query' => ''];
}
$requestPath = $parsedUrl['path'] ?? '/';

// Announce/scrape endpoints do not need language/user login bootstrapping.
if (preg_match('#^/(?:announce|scrape)(?:\.php)?(?:/|$|\?)#', $requestPath)) {
    defined('IN_TRACKER') || define('IN_TRACKER', true);
}

// Resolve whether this is a legacy .php wrapper (FPM executing public/<page>.php)
// or the Laravel fallback entry point (nexus.php).
$scriptFilename = (string) ($_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

$executedScript = basename($scriptFilename);
if ($executedScript === '' || $executedScript === 'nexus.php') {
    $executedScript = basename($scriptName);
}

$isWrapper = ($executedScript !== '' && $executedScript !== 'nexus.php');

// Determine the page and any PATH_INFO (e.g. confirmemail.php/1/md5/email).
// Wrappers that need a non-trivial Laravel route (details.php, comment.php,
// takelogin.php) can set $nexusRoute before requiring this file.
$nexusRoute = (isset($nexusRoute) && is_string($nexusRoute) && $nexusRoute !== '') ? $nexusRoute : null;
$page = '';
$pathInfo = '';

if ($isWrapper) {
    $page = preg_replace('/\.php$/', '', $executedScript) ?? '';
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?? '';

    if ($page !== '' && preg_match('#^/' . preg_quote($executedScript, '#') . '(/.*)$#', $requestPath, $matches)) {
        $pathInfo = $matches[1];
    }
}

if ($nexusRoute !== null) {
    // Wrapper-defined route (e.g. /details/<id>, /login).
    $routePath = $nexusRoute;
    $pathInfo = '';
    if (isset($_SERVER['PATH_INFO'])) {
        unset($_SERVER['PATH_INFO']);
    }
} elseif ($page === '') {
    // Fallback entry point: derive script and PATH_INFO from the URI so
    // /torrents, /torrents.php and /confirmemail/1/md5/email work without
    // a per-page wrapper.
    if ($requestPath === '/' || $requestPath === '') {
        $routePath = '/';
        $pathInfo = '';
    } elseif (preg_match('#^/([a-zA-Z0-9_-]+)(?:\.php)?(/.*)?$#', $requestPath, $matches)) {
        $routePath = '/' . $matches[1];
        $pathInfo = $matches[2] ?? '';
    } else {
        $routePath = $requestPath;
        $pathInfo = (string) ($_SERVER['PATH_INFO'] ?? '');
    }
} else {
    // Wrapper entry point: strip the .php suffix and route to the canonical path.
    $routePath = '/' . $page;

    // Legacy confirmemail.php/<id>/<md5>/<email> needs PATH_INFO preserved.
    if ($pathInfo !== '') {
        $_SERVER['PATH_INFO'] = $pathInfo;
    } elseif (isset($_SERVER['PATH_INFO'])) {
        unset($_SERVER['PATH_INFO']);
    }
}

// Determine the legacy "script" name used for per-page language files,
// parked() guards and autoclean. For direct Laravel routes this is derived
// from the first path segment, otherwise it is the wrapper page name.
if ($isWrapper && $page !== '') {
    $script = $page;
} elseif ($nexusRoute !== null) {
    $script = basename($nexusRoute) ?: 'nexus';
    $script = preg_replace('/[^a-zA-Z0-9_-]/', '', $script) ?? '';
    if ($script === '') {
        $script = 'nexus';
    }
} else {
    $segments = explode('/', trim($routePath, '/'));
    $script = $segments[0] ?? '';
    $script = preg_replace('/[^a-zA-Z0-9_-]/', '', $script) ?? '';
    if ($script === '') {
        $script = 'index';
    }
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Map legacy wrapper query parameters to Laravel path parameters now that the
// per-page wrappers are gone.
if ($script === 'details') {
    if (isset($_GET['id'])) {
        $routePath = '/details/' . (int) $_GET['id'];
        unset($_GET['id']);
    }
} elseif ($script === 'comment') {
    $commentAction = (string) ($_GET['action'] ?? '');
    $commentId = (int) ($_GET['cid'] ?? 0);
    if (in_array($commentAction, ['edit', 'delete', 'vieworiginal'], true)) {
        unset($_GET['action'], $_GET['cid']);
        $routePath = '/comment/' . $commentId . '/' . $commentAction;
    } elseif ($commentAction === 'add' && $method === 'GET') {
        unset($_GET['action']);
        $routePath = '/comment/add';
    } else {
        unset($_GET['action']);
        $routePath = '/comment';
    }
} elseif ($script === 'takelogin') {
    $routePath = '/login';
}

// Build the URI query string from the current GET parameters. Wrappers that
// rewrite the route can modify $_GET before requiring this file (e.g. to drop
// an id that is now encoded in the path).
$queryString = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);

$uri = $routePath . ($queryString !== '' ? '?' . $queryString : '');

// Build the server array passed to Laravel/Symfony. This ensures both the
// Request object and the legacy $_SERVER global agree on SCRIPT_NAME/REQUEST_URI.
$server = $_SERVER;
$server['REQUEST_URI'] = $uri;
$server['REQUEST_METHOD'] = $method;

$server['SCRIPT_NAME'] = '/' . $script . '.php';
$server['SCRIPT_FILENAME'] = __DIR__ . '/' . $script . '.php';
$server['PHP_SELF'] = '/' . $script . '.php' . $pathInfo;

if ($pathInfo !== '') {
    $server['PATH_INFO'] = $pathInfo;
} else {
    unset($server['PATH_INFO']);
}

// Mirror the normalized values back to the global $_SERVER for legacy helpers
// such as Nexus::getScript() and get_langfile_path().
$_SERVER['REQUEST_URI'] = $server['REQUEST_URI'];
$_SERVER['SCRIPT_NAME'] = $server['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $server['SCRIPT_FILENAME'];
$_SERVER['PHP_SELF'] = $server['PHP_SELF'];
if (isset($server['PATH_INFO'])) {
    $_SERVER['PATH_INFO'] = $server['PATH_INFO'];
} elseif (isset($_SERVER['PATH_INFO'])) {
    unset($_SERVER['PATH_INFO']);
}

$parameters = ($method === 'POST') ? $_POST : $_GET;
$files = ($method === 'POST') ? $_FILES : [];

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}

require_once __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$request = Request::create($uri, $method, $parameters, $_COOKIE, $files, $server);

// Legacy bootstrap: cache, Eloquent, settings, language, login and plugins,
// wired into SupportContext instead of $GLOBALS.
LegacyBootstrap::boot($request, $rootpath);

$script = nexus()->getScript();

// Load the page-specific language file(s) the legacy wrappers used to require.
$extraLangFiles = [
    'search' => ['torrents.php'],
    'shoutbox_history' => ['shoutbox.php'],
    'special' => ['torrents.php'],
    'torrents' => ['special.php'],
    'take-increment-bulk' => ['increment-bulk.php'],
    'upload' => ['edit.php'],
];
$scriptLangFiles = array_unique(array_merge([$script . '.php'], $extraLangFiles[$script] ?? []));
foreach ($scriptLangFiles as $scriptLangFile) {
    $langPath = $rootpath . get_langfile_path($scriptLangFile);
    if (is_file($langPath)) {
        require_once $langPath;
    }
}

// Synchronise any per-script language globals into the context so helpers can
// read them without touching $GLOBALS directly.
SupportContext::fromGlobals();

// Replicate legacy per-page parked() guards.
$parkedScripts = [
    'viewsnatches', 'users', 'special', 'forums', 'report', 'cheaterbox', 'upload',
    'offers', 'comment', 'userdetails', 'checkuser', 'invite', 'bitbucket-upload',
    'mybonus', 'userhistory', 'moresmilies', 'torrents', 'getattachment',
    'sendmessage', 'reports', 'self-enable', 'friends', 'settings', 'topten', 'attendance',
];
if (in_array($script, $parkedScripts, true)) {
    \parked();
}

// Autoclean only ran on the legacy index.php wrapper.
if ($script === 'index') {
    register_shutdown_function('autoclean');
}

$kernel = $app->make(Kernel::class);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
