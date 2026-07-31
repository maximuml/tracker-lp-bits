<?php
require_once("../include/bittorrent.php");
dbconn();

if (! isset($CURUSER)) {
    http_response_code(403);
    exit;
}

$type = $_GET['type'] ?? 'shoutbox';
$lastId = (int) ($_GET['last_id'] ?? $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
$maxLoops = 60; // 60 * 2s = 120s
$userId = (int) ($CURUSER['id'] ?? 0);

// Limit each user to one concurrent SSE stream so the PHP-FPM pool cannot be
// exhausted by opening many connections. Advisory file locks are released
// automatically when the script/connection ends.
$lockFile = sys_get_temp_dir() . '/shoutbox_sse_' . $userId . '.lock';
$lockHandle = @fopen($lockFile, 'c');
if ($lockHandle !== false && ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
    http_response_code(429);
    exit;
}
if ($lockHandle !== false) {
    register_shutdown_function(function () use ($lockHandle) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    });
}

function buildShoutboxQuery(string $type, int $lastId): object
{
    $query = \Nexus\Database\NexusDB::table('shoutbox')
        ->orderBy('id')
        ->where('id', '>', $lastId);
    \App\Support\Shoutbox::applyTypeFilter($query, $type, $GLOBALS['CURUSER'] ?? null);
    return $query;
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
        // Re-bind the query for the next batch.
        $query = buildShoutboxQuery($type, $lastId);
    }

    echo "event: ping\n";
    echo "data: {}\n\n";
    flushOutput();

    sleep(2);
}

function flushOutput(): void
{
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}
