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

$returnto = $_GET['returnto'] ?? '';
$returntoHtml = htmlspecialchars($returnto);
if (!empty($_GET['uploaded'])) {
    echo '<div class="d2-banner d2-banner--success"><h1 align="center">' . $lang_details['text_successfully_uploaded'] . '</h1><p>' . $lang_details['text_redownload_torrent_note'] . '</p></div>';
    echo '<meta http-equiv="refresh" content="1;url=download.php?id=' . $id . '">'; // fallback after body start may not send header, use meta
} elseif (!empty($_GET['edited'])) {
    echo '<div class="d2-banner d2-banner--success"><h1 align="center">' . $lang_details['text_successfully_edited'] . '</h1>';
    if ($returntoHtml) {
        echo '<p><b>' . $lang_details['text_go_back'] . '<a href="' . $returntoHtml . '">' . $lang_details['text_whence_you_came'] . '</a></b></p>';
    }
    echo '</div>';
} elseif (!empty($_GET['existed'])) {
    echo '<div class="d2-banner d2-banner--warning"><h1 align="center">' . $lang_details['torrent_existed'] . '</h1>';
    if ($returntoHtml) {
        echo '<p><b>' . $lang_details['text_go_back'] . '<a href="' . $returntoHtml . '">' . $lang_details['text_whence_you_came'] . '</a></b></p>';
    }
    echo '</div>';
}

echo \App\Support\TorrentDetails::render($row, $returnto);
stdfoot();
