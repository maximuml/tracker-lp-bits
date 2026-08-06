<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


header('Content-Type: application/json; charset=utf-8');

$name = trim($_GET['name'] ?? $_POST['name'] ?? '');
$url = trim($_GET['url'] ?? $_POST['url'] ?? '');

if (!$name && !$url) {
    echo json_encode(['success' => false, 'error' => 'Torrent name or setlist URL is required.']);
    return;
}

try {
    if ($url) {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (!in_array(strtolower($host), ['www.setlist.fm', 'setlist.fm'], true)) {
            echo json_encode(['success' => false, 'error' => 'Only setlist.fm URLs are allowed.']);
            return;
        }
        $result = \App\Support\SetlistLookup::fromUrl($url);
    } else {
        $result = \App\Support\SetlistLookup::fromTorrentName($name);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    do_log($e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
    echo json_encode(['success' => false, 'error' => 'Setlist lookup failed.']);
}
