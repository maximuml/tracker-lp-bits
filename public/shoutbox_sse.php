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

$query = \Nexus\Database\NexusDB::table('shoutbox')->orderBy('id')->where('id', '>', $lastId);
if ($type == 'helpbox' && $showhelpbox_main == 'yes') {
    $query->where('type', 'hb');
} elseif ($type == 'shoutbox') {
    $query->where('type', 'sb');
}

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

$start = time();
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
        flush();
        $lastId = $maxId;
        // Re-bind the query for the next batch.
        $query = \Nexus\Database\NexusDB::table('shoutbox')->orderBy('id')->where('id', '>', $lastId);
        if ($type == 'helpbox' && $showhelpbox_main == 'yes') {
            $query->where('type', 'hb');
        } elseif ($type == 'shoutbox') {
            $query->where('type', 'sb');
        }
    }

    echo "event: ping\n";
    echo "data: {}\n\n";
    flush();

    sleep(2);
}
