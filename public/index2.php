<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path('functions.php'));
require_once(get_langfile_path('index.php'));
loggedinorreturn(true);

$searchBoxId = 1;
$limit = 12;

$rows = \App\Models\Torrent::query()
    ->where('visible', 'yes')
    ->where('banned', 'no')
    ->orderBy('added', 'desc')
    ->limit($limit)
    ->get()
    ->toArray();

stdhead($lang_index['head_index'] ?? 'Home');
\Nexus\Nexus::css('styles/torrents2.css', 'header', true);
\Nexus\Nexus::css('styles/index2.css', 'header', true);
begin_main_frame();

echo '<div class="t2-wrap">';
echo '<h1 class="i2-latest-title">' . ($lang_index['text_latest_torrents'] ?? 'Latest torrents') . '</h1>';
echo \App\Support\TorrentGrid::render($rows, 'card', $searchBoxId);
echo '</div>';

end_main_frame();
stdfoot();
