<?php global $USERUPDATESET;
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");

?>

@include('torrents._search_form')

<?php
if($inclbookmarked == 1)
{
	print("<h1 align=\"center\">" . get_username($CURUSER['id']) . $lang_torrents['text_s_bookmarked_torrent'] . "</h1>");
}
elseif($inclbookmarked == 2)
{
	print("<h1 align=\"center\">" . get_username($CURUSER['id']) . $lang_torrents['text_s_not_bookmarked_torrent'] . "</h1>");
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
		torrenttable($rows, "torrents", $sectiontype);
	elseif ($sectiontype == $specialcatmode)
		torrenttable($rows, "music", $sectiontype);
	else torrenttable($rows, "bookmarks", $sectiontype);
	print($pagerbottom);
}
else {
	if (isset($searchstr)) {
		print("<br />");
		stdmsg($lang_torrents['std_search_results_for'] . $searchstr_ori . "\"",$lang_torrents['std_try_again']);
	}
	else {
		stdmsg($lang_torrents['std_nothing_found'],$lang_torrents['std_no_active_torrents']);
	}
}
if ($CURUSER){
	if ($sectiontype == $browsecatmode)
		$USERUPDATESET['last_browse'] = TIMENOW;
	else	$USERUPDATESET['last_music'] = TIMENOW;
}
print("</td></tr></table>");
