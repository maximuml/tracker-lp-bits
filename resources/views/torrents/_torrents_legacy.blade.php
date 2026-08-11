<?php
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");

?>

@include('torrents._search_form')

<?php
if($inclbookmarked == 1)
{
	print("<h1 align=\"center\">" . \App\Support\UserDisplay::username($CURUSER['id']) . $lang_torrents['text_s_bookmarked_torrent'] . "</h1>");
}
elseif($inclbookmarked == 2)
{
	print("<h1 align=\"center\">" . \App\Support\UserDisplay::username($CURUSER['id']) . $lang_torrents['text_s_not_bookmarked_torrent'] . "</h1>");
}

if ($count) {
    $rows = [];
    if ($shouldUseMeili) {
        $rows = $resultFromSearchRep['list'];
    } else {
        $fieldsArr = \App\Models\Torrent::getFieldsForList(true);
        $rows = \App\Repositories\TorrentListingRepository::getList(array_merge($listingOptions, [
            'fields' => $fieldsArr,
            'search_box_id' => $sectiontype,
            'order_by' => $orderby,
            'offset' => $offset,
            'limit' => $size,
        ]));
    }
    $rows = apply_filter('torrent_list', $rows, $page, $sectiontype, $searchstr_raw);
	print($pagertop);
	if ($sectiontype == $browsecatmode)
		echo \App\Support\TorrentTable::render($rows, "torrents", $sectiontype);
	else echo \App\Support\TorrentTable::render($rows, "bookmarks", $sectiontype);
	print($pagerbottom);
}
else {
	if (isset($searchstr)) {
		print("<br />");
		\App\Support\Html::stdMessage($lang_torrents['std_search_results_for'] . $searchstr_ori . "\"", $lang_torrents['std_try_again']);
	}
	else {
		\App\Support\Html::stdMessage($lang_torrents['std_nothing_found'], $lang_torrents['std_no_active_torrents']);
	}
}
if ($CURUSER){
	if ($sectiontype == $browsecatmode)
		\App\Support\SupportContext::addUserUpdate('last_browse', TIMENOW);
	else	\App\Support\SupportContext::addUserUpdate('last_music', TIMENOW);
}
print("</td></tr></table>");
