<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-Type: application/json; charset=utf-8");

$torrents = [];

$query = trim(\App\Support\SupportContext::getQuery('q') ?? '');
if ($query !== '' && strlen($query) >= 2 && \App\Support\Config\SiteConfig::current()->meiliSearch->enabled()) {
    try {
        $user = \App\Models\User::query()->find($CURUSER['id']);
        if ($user) {
            $rep = new \App\Repositories\MeiliSearchRepository();
            $torrents = $rep->autocomplete($query, 10, $user);
        }
    } catch (\Throwable $e) {
        \App\Support\Logger::writeWithContext((string) ('MeiliSearch autocomplete error: ' . $e->getMessage()), (string) 'error', (bool) false);
    }
}

echo json_encode(['torrents' => $torrents]);
