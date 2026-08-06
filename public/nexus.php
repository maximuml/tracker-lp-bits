<?php

use App\Support\SupportContext;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
defined('IN_NEXUS') || define('IN_NEXUS', true);

$rootpath = dirname(__DIR__) . '/';
set_include_path(get_include_path() . PATH_SEPARATOR . $rootpath);

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
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$parsedUrl = parse_url($requestUri);
if ($parsedUrl === false) {
    $parsedUrl = ['path' => '/', 'query' => ''];
}
$requestPath = $parsedUrl['path'] ?? '/';

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
    // Fallback entry point: keep the clean URI as-is.
    $routePath = $requestPath;
    $pathInfo = (string) ($_SERVER['PATH_INFO'] ?? '');
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

// Build the URI query string from the current GET parameters. Wrappers that
// rewrite the route can modify $_GET before requiring this file (e.g. to drop
// an id that is now encoded in the path).
$queryString = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);

$uri = $routePath . ($queryString !== '' ? '?' . $queryString : '');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Build the server array passed to Laravel/Symfony. This ensures both the
// Request object and the legacy $_SERVER global agree on SCRIPT_NAME/REQUEST_URI.
$server = $_SERVER;
$server['REQUEST_URI'] = $uri;
$server['REQUEST_METHOD'] = $method;

if ($isWrapper && $page !== '') {
    $server['SCRIPT_NAME'] = '/' . $page . '.php';
    $server['SCRIPT_FILENAME'] = __DIR__ . '/' . $page . '.php';
    $server['PHP_SELF'] = '/' . $page . '.php' . $pathInfo;

    if ($pathInfo !== '') {
        $server['PATH_INFO'] = $pathInfo;
    } else {
        unset($server['PATH_INFO']);
    }
} else {
    if (empty($server['SCRIPT_NAME'])) {
        $server['SCRIPT_NAME'] = '/nexus.php';
    }
    if (empty($server['SCRIPT_FILENAME'])) {
        $server['SCRIPT_FILENAME'] = __FILE__;
    }
    if (empty($server['PHP_SELF'])) {
        $server['PHP_SELF'] = '/nexus.php';
    }
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

// Legacy bootstrap: settings, cache, language, user login and globals.
// This is what the old per-page `require '../include/bittorrent.php'; dbconn();`
// block used to do; centralising it here lets every wrapper become one line.
require_once $rootpath . 'include/core.php';
require_once $rootpath . 'classes/class_attendance.php';

// Load the page-specific language file(s) the legacy wrappers used to require.
$script = nexus()->getScript();
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

// Synchronise the legacy global state into the context object so helpers can
// read it without touching $GLOBALS directly.
SupportContext::fromGlobals();

$kernel = $app->make(Kernel::class);

$request = Request::create($uri, $method, $parameters, $_COOKIE, $files, $server);

SupportContext::fromRequest($request);

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

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
