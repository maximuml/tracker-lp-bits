<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
$brsectiontype = $browsecatmode;
$showsubcat = get_searchbox_value($brsectiontype, 'showsubcat');
$showsource = get_searchbox_value($brsectiontype, 'showsource'); //whether show sources or not
$showmedium = get_searchbox_value($brsectiontype, 'showmedium'); //whether show media or not
$showcodec = get_searchbox_value($brsectiontype, 'showcodec'); //whether show codecs or not
$showstandard = get_searchbox_value($brsectiontype, 'showstandard'); //whether show standards or not
$showprocessing = get_searchbox_value($brsectiontype, 'showprocessing'); //whether show processings or not
$showaudiocodec = get_searchbox_value($brsectiontype, 'showaudiocodec'); //whether show audio codecs or not
$catsperrow = (int)get_searchbox_value($brsectiontype, 'catsperrow'); //show how many cats per line

$catpadding = get_searchbox_value($brsectiontype, 'catpadding'); //padding space between categories in pixel

$brcats = genrelist($brsectiontype);

if ($showsubcat){
if ($showsource) $sources = searchbox_item_list("sources", $brsectiontype);
if ($showmedium) $media = searchbox_item_list("media", $brsectiontype);
if ($showcodec) $codecs = searchbox_item_list("codecs", $brsectiontype);
if ($showstandard) $standards = searchbox_item_list("standards", $brsectiontype);
if ($showprocessing) $processings = searchbox_item_list("processings", $brsectiontype);
if ($showaudiocodec) $audiocodecs = searchbox_item_list("audiocodecs", $brsectiontype);
}
stdhead($lang_getrss['head_rss_feeds']);
$query = [];
$allowed_showrows=array('10','50');
$stickyTypes = [
    0 => nexus_trans('torrent.pos_state_normal'),
    1 => nexus_trans('torrent.pos_state_sticky'),
    2 => nexus_trans('torrent.pos_state_r_sticky')
];
$query[] = "passkey=" . $CURUSER['passkey'];
if ($__server_REQUEST_METHOD == "POST") {
	$link = get_protocol_prefix(). $BASEURL ."/torrentrss.php";
	if (((\App\Support\SupportContext::getPost('showrows') !== null)) && in_array(\App\Support\SupportContext::getPost('showrows'), $allowed_showrows, 1))
		$query[] = "rows=".(int)\App\Support\SupportContext::getPost('showrows');
	else {
		stdmsg($lang_getrss['std_error'],$lang_getrss['std_no_row']);
		stdfoot();
		die();
	}
	foreach ($brcats as $cat)
	{
		if (!empty(\App\Support\SupportContext::getPost("cat{$cat['id']}")))
		{
			$query[] = "cat{$cat['id']}=1";
		}
	}
	if ($showsubcat){
		if ($showsource)
		foreach ($sources as $source)
		{
			if (!empty(\App\Support\SupportContext::getPost("sou{$source['id']}")))
			{
				$query[] = "sou{$source['id']}=1";
			}
		}
		if ($showmedium)
		foreach ($media as $medium)
		{
			if (!empty(\App\Support\SupportContext::getPost("med{$medium['id']}")))
			{
				$query[] = "med{$medium['id']}=1";
			}
		}
		if ($showcodec)
		foreach ($codecs as $codec)
		{
			if (!empty(\App\Support\SupportContext::getPost("cod{$codec['id']}")))
			{
				$query[] = "cod{$codec['id']}=1";
			}
		}
		if ($showstandard)
		foreach ($standards as $standard)
		{
			if (!empty(\App\Support\SupportContext::getPost("sta{$standard['id']}")))
			{
				$query[] = "sta{$standard['id']}=1";
			}
		}
		if ($showprocessing)
		foreach ($processings as $processing)
		{
			if (!empty(\App\Support\SupportContext::getPost("pro{$processing['id']}")))
			{
				$query[] = "pro{$processing['id']}=1";
			}
		}
		if ($showaudiocodec)
		foreach ($audiocodecs as $audiocodec)
		{
			if (!empty(\App\Support\SupportContext::getPost("aud{$audiocodec['id']}")))
			{
				$query[] = "aud{$audiocodec['id']}=1";
			}
		}
	}
	if (!empty(\App\Support\SupportContext::getPost("itemcategory")))
	{
		$query[] = "icat=1";
	}
	if (!empty(\App\Support\SupportContext::getPost("itemsmalldescr")))
	{
		$query[] = "ismalldescr=1";
	}
	if (!empty(\App\Support\SupportContext::getPost("itemsize")))
	{
		$query[] = "isize=1";
	}
	if (!empty(\App\Support\SupportContext::getPost("itemuploader")))
	{
		$query[] = "iuplder=1";
	}
	$searchstr = \Nexus\Database\NexusDB::getInstance()->escapeString(trim(\App\Support\SupportContext::getPost("search") ?? ''));
//	if (empty($searchstr))
//		unset($searchstr);
	if ($searchstr)
	{
		$query[] = "search=".rawurlencode($searchstr);
		if (\App\Support\SupportContext::getPost("search_mode")){
			$search_mode = intval(\App\Support\SupportContext::getPost("search_mode") ?? 0);
			if (!in_array($search_mode,array(0,2)))
			{
				$search_mode = 0;
			}
			$query[] = "search_mode=".$search_mode;
		}
	}
	if (!empty(\App\Support\SupportContext::getPost('sticky')) && is_array(\App\Support\SupportContext::getPost('sticky'))) {
	    $query[] = "sticky=" . implode(',', \App\Support\SupportContext::getPost('sticky'));
    }
    if (((\App\Support\SupportContext::getPost('paid') !== null))) {
        $query[] = "paid=" . \App\Support\SupportContext::getPost('paid');
    }
	$inclbookmarked=intval(\App\Support\SupportContext::getPost('inclbookmarked') ?? 0);
	if($inclbookmarked)
	{
		if (!in_array($inclbookmarked,array(0,1)))
		{
			$inclbookmarked = 0;
		}
		$addinclbm = "&inclbookmarked=".$inclbookmarked;
	}
	else
	{
		$addinclbm="";
	}
	$queries = implode("&", $query);
	if ($queries)
		$link .= "?".$queries;
	$msg = $lang_getrss['std_use_following_url'] ."\n".$link."\n\n".$lang_getrss['std_utorrent_feed_url']."\n".$link."&linktype=dl".$addinclbm;
	stdmsg($lang_getrss['std_done'],format_comment($msg));
	stdfoot();
	die();
}

?>
<h1 align="center"><?php echo $lang_getrss['text_rss_feeds']?></h1>
<form method="post" action="getrss.php">
<table cellspacing="1" cellpadding="5" width="97%">
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_categories_to_retrieve']?>
</td>
<td class="rowfollow" align="left">
<?php
/*
$categories = "<table><tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_category']."</b></td></tr><tr>";
$i = 0;
foreach ($brcats as $cat)//print category list of Torrents section
{
	$numinrow = $i % $catsperrow;
	$rownum = (int)($i / $catsperrow);
	if ($i && $numinrow == 0){
		$categories .= "</tr>".($brenablecatrow ? "<tr><td class=\"embedded\" align=\"left\"><b>".$brcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
	}
	$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cat".$cat['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked=\"checked\"" : "")." value='yes' />".return_category_image($cat['id'], "torrents.php?allsec=1&amp;")."</td>\n";
	$i++;
}
$categories .= "</tr>";
			if ($showsubcat)//Show subcategory (i.e. source, codecs) selections
			{
				if ($showsource){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_source']."</b></td></tr><tr>";
				$i = 0;
				foreach ($sources as $source)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sou".$source['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sou".$source['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$source['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showmedium){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_medium']."</b></td></tr><tr>";
				$i = 0;
				foreach ($media as $medium)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"med".$medium['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[med".$medium['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$medium['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showcodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($codecs as $codec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cod".$codec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cod".$codec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$codec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showaudiocodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_audio_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($audiocodecs as $audiocodec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"aud".$audiocodec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[aud".$audiocodec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$audiocodec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showstandard){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_standard']."</b></td></tr><tr>";
				$i = 0;
				foreach ($standards as $standard)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sta".$standard['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sta".$standard['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$standard['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showprocessing){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_processing']."</b></td></tr><tr>";
				$i = 0;
				foreach ($processings as $processing)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"pro".$processing['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[pro".$processing['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$processing['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
			}
$categories .= "</table>";
*/

$categories = build_search_box_category_table($browsecatmode, 'yes', 'torrents.php?allsec=1&', false, 3, '', ['section_name' => true]);
print($categories);
?>
</td>
</tr>
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_show_bookmarked']?>
</td>
<td class="rowfollow" align="left">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0"><?php echo $lang_getrss['text_all']?></label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1"><?php echo $lang_getrss['text_only_bookmarked']?></label><div><?php echo $lang_getrss['text_show_bookmarked_note']?></div>
</td>
</tr>
    <tr>
        <td class="rowhead"><?php echo $lang_getrss['row_sticky']?>
        </td>
        <td class="rowfollow" align="left">
            <?php
                foreach ($stickyTypes as $key => $value) {
                    echo sprintf('<label><input type="checkbox" name="sticky[]" value="%s">%s</label>', $key, $value);
                }
            ?>
        </td>
    </tr>
<tr>
    <?php if(get_setting("torrent.paid_torrent_enabled") == "yes"){?>
<tr>
    <td class="rowhead"><?php echo $lang_getrss['row_paid']?>
    </td>
    <td class="rowfollow" align="left">
        <label><input type="radio" name="paid" value="0" checked><?php echo $lang_getrss['paid_no']?></label>
        <label><input type="radio" name="paid" value="1"><?php echo $lang_getrss['paid_yes']?></label>
        <label><input type="radio" name="paid" value="2"><?php echo $lang_getrss['paid_all']?></label>
        <div><?php echo $lang_getrss['row_paid_help'] ?></div>
    </td>
</tr>
    <?php }?>
<td class="rowhead"><?php echo $lang_getrss['row_item_title_type']?>
</td>
<td class="rowfollow" align="left">
<input type="checkbox" name="itemcategory" value="1" /><?php echo $lang_getrss['text_item_category']?>&nbsp;<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" /><?php echo $lang_getrss['text_item_title']?>&nbsp;<input type="checkbox" name="itemsize" value="1" /><?php echo $lang_getrss['text_item_size']?>&nbsp;<input type="checkbox" name="itemuploader" value="1" /><?php echo $lang_getrss['text_item_uploader']?>
</td>
</tr>
<tr><td class="rowhead"><?php echo $lang_getrss['row_rows_per_page']?></td><td class="rowfollow" align="left"><select name="showrows">
<?php
    foreach ($allowed_showrows as $showrow) {
        echo sprintf('<option value="%s">%s</option>', $showrow, $showrow);
    }
?>
</select></td></tr>
<tr>
<td colspan="2" align="center">
<input type="submit" value="<?php echo $lang_getrss['submit_generatte_rss_link']?>" />
</td>
</tr>
</table>
</form>
<?php
stdfoot();
