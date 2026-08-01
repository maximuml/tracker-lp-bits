<?php
ob_start();
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path('details.php'));
global $CURUSER, $lang_details;
loggedinorreturn();

$id = intval($_GET["id"] ?? 0);
int_check($id, true);
if (!$id) {
    die();
}

$row = \App\Repositories\TorrentDetailRepository::getTorrent($id);
if (!$row) {
    stderr($lang_details['std_error'], $lang_details['std_no_torrent_id']);
}

if (
    ($row['banned'] == 'yes' && !user_can('seebanned') && $row['owner'] != $CURUSER['id'])
    || (!can_access_torrent($row, $CURUSER['id']) && $row['owner'] != $CURUSER['id'])
) {
    permissiondenied();
}

$row = apply_filter('torrent_detail', $row);

if (!empty($_GET["hit"])) {
    \App\Repositories\TorrentDetailRepository::incrementViews($id);
}

$assetVersion = max(
    filemtime(__DIR__ . '/styles/details2.css') ?: 0,
    filemtime(__DIR__ . '/js/details2.js') ?: 0
);
\Nexus\Nexus::css('styles/details2.css?v=' . $assetVersion, 'header', true);
\Nexus\Nexus::js('js/details2.js?v=' . $assetVersion, 'footer', true);
stdhead($lang_details['head_details_for_torrent'] . '"' . $row["name"] . '"');
echo \App\Support\TorrentDetails::render($row);
stdfoot();
