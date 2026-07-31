<?php
require_once("../include/bittorrent.php");
dbconn();

if (! isset($CURUSER)) {
    http_response_code(403);
    exit;
}

$type = $_GET['type'] ?? 'shoutbox';
$lastId = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['last_id'] ?? 0);
$userId = (int) ($CURUSER['id'] ?? 0);

// Keep PHP-FPM worker time for a single stream short; browsers reconnect automatically.
$maxLoops = 30; // 30 * 2s = 60s
$ttl = $maxLoops * 2 + 10;

$maxStreams = 30;
$globalKey = 'shoutbox_sse_global';
$redis = \Nexus\Database\NexusDB::redis();

function sseShutdown(\Redis $redis, string $globalKey): void {
    try {
        $redis->decr($globalKey);
    } catch (\Throwable $e) {
    }
}

$active = (int) $redis->incr($globalKey);
if ($active === 1) {
    $redis->expire($globalKey, $ttl + 60);
}
if ($active > $maxStreams) {
    sseShutdown($redis, $globalKey);
    http_response_code(503);
    exit;
}
// Limit each user to one concurrent SSE stream.
$userLock = new \Nexus\Database\NexusLock('shoutbox_sse:' . $userId, $ttl);
if (! $userLock->acquire()) {
    sseShutdown($redis, $globalKey);
    http_response_code(429);
    exit;
}

register_shutdown_function(function () use ($redis, $globalKey, $userLock) {
    try {
        $userLock->release();
    } catch (\Throwable $e) {
    }
    sseShutdown($redis, $globalKey);
});

function buildShoutboxQuery(string $type, int $lastId): object
{
    $query = \Nexus\Database\NexusDB::table('shoutbox')
        ->orderBy('id')
        ->where('id', '>', $lastId);
    \App\Support\Shoutbox::applyTypeFilter($query, $type, $GLOBALS['CURUSER'] ?? null);
    return $query;
}

function flushOutput(): void
{
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

$query = buildShoutboxQuery($type, $lastId);

@ini_set('zlib.output_compression', 'Off');
while (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

set_time_limit(0);
ignore_user_abort(true);

for ($i = 0; $i < $maxLoops; $i++) {
    if (connection_aborted()) {
        break;
    }

    $rows = $query->get();
    if (! $rows->isEmpty()) {
        $maxId = (int) $rows->last()->id;
        echo "id: " . $maxId . "\n";
        echo "event: refresh\n";
        echo "data: " . json_encode(['count' => $rows->count()]) . "\n\n";
        flushOutput();
        $lastId = $maxId;
        $query = buildShoutboxQuery($type, $lastId);
    }

    echo "event: ping\n";
    echo "data: {}\n\n";
    flushOutput();

    sleep(2);
}
