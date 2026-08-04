<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());

loggedinorreturn();
parked();

$rootpath = dirname(__DIR__) . '/';

if (! class_exists(\Illuminate\Http\Request::class)) {
    require_once $rootpath . 'vendor/autoload.php';
}

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$commentId = (int) ($_GET['cid'] ?? 0);

$uri = match ($action) {
    'add' => $method === 'POST' ? '/comment' : '/comment/add',
    'edit' => '/comment/' . $commentId . '/edit',
    'delete' => '/comment/' . $commentId . '/delete',
    'vieworiginal' => '/comment/' . $commentId . '/original',
    default => '/comment',
};

$query = $_GET;
unset($query['action'], $query['cid']);
$post = $_POST;

$server = $_SERVER;
$server['REQUEST_URI'] = $uri . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
$server['SCRIPT_NAME'] = '/comment.php';
$server['SCRIPT_FILENAME'] = $rootpath . 'public/comment.php';
$server['REQUEST_METHOD'] = $method;

$request = \Illuminate\Http\Request::create(
    $uri,
    $method,
    $method === 'POST' ? $post : $query,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
