<?php global $USERUPDATESET;
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");


$searchBoxRightTdStyle = 'padding: 1px;padding-left: 10px;white-space: nowrap';
if ($allsec != 1 || $enablespecial != 'yes'){ //do not print searchbox if showing bookmarked torrents from all sections;
?>
<form method="get" name="searchbox" action="?">
	<table border="1" class="searchbox" cellspacing="0" cellpadding="5" width="100%">
		<tbody>
		<tr>
		<td class="colhead" align="center" colspan="2"><a href="javascript: klappe_news('searchboxmain')"><img class="plus" src="pic/trans.gif" id="picsearchboxmain" alt="Show/Hide" /><?php echo $lang_torrents['text_search_box'] ?></a></td>
		</tr></tbody>
		<tbody id="ksearchboxmain" style="display:none">
		<tr>
			<td class="rowfollow" align="left">
<!--				<table>-->
<!--					--><?php
//						function printcat($name, $listarray, $cbname, $wherelistina, $btname, $showimg = false)
//						{
//							global $catpadding,$catsperrow,$lang_torrents,$CURUSER,$CURLANGDIR,$catimgurl;
//
//							print("<tr><td class=\"embedded\" colspan=\"".$catsperrow."\" align=\"left\"><b>".$name."</b></td></tr><tr>");
//							$i = 0;
//							foreach($listarray as $list){
//								if ($i && $i % $catsperrow == 0){
//									print("</tr><tr>");
//								}
//								print("<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px; padding-left: ".$catpadding."px;\"><input type=\"checkbox\" id=\"".$cbname.$list['id']."\" name=\"".$cbname.$list['id']."\"" . (in_array($list['id'],$wherelistina) ? " checked=\"checked\"" : "") . " value=\"1\" />".($showimg ? return_category_image($list['id'], "?") : "<a title=\"" .$list['name'] . "\" href=\"?".$cbname."=".$list['id']."\">".$list['name']."</a>")."</td>\n");
//								$i++;
//							}
//							$checker = "<input name=\"".$btname."\" value='" .  $lang_torrents['input_check_all'] . "' class=\"btn medium\" type=\"button\" onclick=\"javascript:SetChecked('".$cbname."','".$btname."','". $lang_torrents['input_check_all'] ."','" . $lang_torrents['input_uncheck_all'] . "',-1,10)\" />";
//							print("<td colspan=\"2\" class=\"bottom\" align=\"left\" style=\"padding-left: 15px\">".$checker."</td>\n");
//							print("</tr>");
//						}
//					printcat($lang_torrents['text_category'],$cats,"cat",$wherecatina,"cat_check",true);
//
//					if ($showsubcat){
//						if ($showsource)
//							printcat($lang_torrents['text_source'], $sources, "source", $wheresourceina, "source_check");
//						if ($showmedium)
//							printcat($lang_torrents['text_medium'], $media, "medium", $wheremediumina, "medium_check");
//						if ($showcodec)
//							printcat($lang_torrents['text_codec'], $codecs, "codec", $wherecodecina, "codec_check");
//						if ($showaudiocodec)
//							printcat($lang_torrents['text_audio_codec'], $audiocodecs, "audiocodec", $whereaudiocodecina, "audiocodec_check");
//						if ($showstandard)
//							printcat($lang_torrents['text_standard'], $standards, "standard", $wherestandardina, "standard_check");
//						if ($showprocessing)
//							printcat($lang_torrents['text_processing'], $processings, "processing", $whereprocessingina, "processing_check");
//					}
//					?>
<!--				</table>-->
                <?php echo build_search_box_category_table($sectiontype, '1', '?', '?', 0, $_SERVER['QUERY_STRING'], ['select_unselect' => true, 'user_notifs' => $CURUSER['notifs']])?>
			</td>

			<td class="rowfollow" valign="middle">
				<table>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_dead_active'] ?></font>
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="incldead" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_including_dead'] ?></option>
								<option value="1"<?php print($include_dead == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_active'] ?> </option>
								<option value="2"<?php print($include_dead == 2 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_dead'] ?></option>
							</select>
						</td>
				 	</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_special_torrents'] ?></font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="spstate" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_all'] ?></option>
<?php echo promotion_selection($special_state, 0)?>
							</select>
						</td>
					</tr>
					<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<font class="medium"><?php echo $lang_torrents['text_show_bookmarked'] ?></font>
						</td>
				 	</tr>
				 	<tr>
						<td class="bottom" style="padding: 1px;padding-left: 10px">
							<select class="med" name="inclbookmarked" style="width: 100px;">
								<option value="0"><?php echo $lang_torrents['select_all'] ?></option>
								<option value="1"<?php print($inclbookmarked == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_bookmarked'] ?></option>
								<option value="2"<?php print($inclbookmarked == 2 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_bookmarked_exclude'] ?></option>
							</select>
						</td>
					</tr>
                    <?php if ($showApprovalStatusFilter) {?>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <font class="medium"><?php echo $lang_torrents['text_approval_status'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="padding: 1px;padding-left: 10px">
                            <select class="med" name="approval_status" style="width: 100px;">
                                <option value=""><?php echo $lang_torrents['select_all'] ?></option>
                                <?php
                                foreach (\App\Models\Torrent::listApprovalStatus(true) as $key => $value) {
                                    printf('<option value="%s"%s>%s</option>', $key, isset($approvalStatus) && (string)$approvalStatus === (string)$key ? ' selected' : '', $value);
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <?php }?>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['size_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="size_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['size_begin'] ?? '') ?>"/> ~ <input type="number" min="1" name="size_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['size_end'] ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['seeders_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="seeders_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['seeders_begin'] ?? '') ?>"/> ~ <input type="number" min="1" name="seeders_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['seeders_end'] ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['leechers_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="leechers_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['leechers_begin'] ?? '') ?>"/> ~ <input type="number" min="1" name="leechers_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['leechers_end'] ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['times_completed_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="times_completed_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['times_completed_begin'] ?? '') ?>"/> ~ <input type="number" min="1" name="times_completed_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars($_GET['times_completed_end'] ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['added_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <?php echo sprintf(
                                '%s ~ %s',
                                datetimepicker_input('added_begin', htmlspecialchars($_GET['added_begin'] ?? ''), '', ['require_files' => true, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                                datetimepicker_input('added_end', htmlspecialchars($_GET['added_end'] ?? ''), '', ['require_files' => false, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                            ) ?>
                        </td>
                    </tr>

				</table>
			</td>
		</tr>
		</tbody>
		<tbody>
		<tr>
			<td class="rowfollow" align="center">
				<table>
					<tr>
						<td class="embedded">
							<?php echo $lang_torrents['text_search'] ?>&nbsp;&nbsp;
						</td>
						<td class="embedded">
							<table>
								<tr>
									<td class="embedded">
										<input id="searchinput" name="search" type="text" value="<?php echo  $searchstr_ori ?>" autocomplete="off" style="width: 200px" oninput="meiliSuggestInput(this.value)" onkeydown="meiliSuggestKey(event)"/>
										<script src="js/meili_autocomplete.js" type="text/javascript"></script>
									</td>
								</tr>
							</table>
						</td>
						<td class="embedded">
							<?php echo "&nbsp;" . $lang_torrents['text_in'] ?>

							<select name="search_area">
								<option value="0"><?php echo $lang_torrents['select_title'] ?></option>
								<option value="1"<?php print(isset($_GET["search_area"]) && $_GET["search_area"] == 1 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_description'] ?></option>
								<option value="3"<?php print(isset($_GET["search_area"]) && $_GET["search_area"] == 3 ? " selected=\"selected\"" : ""); ?>><?php echo $lang_torrents['select_uploader'] ?></option>
							</select>

							<?php echo $lang_torrents['text_with'] ?>

							<select name="search_mode" style="width: 60px;">
                                <?php echo \App\Models\SearchBox::listSelectModeOptions($_GET["search_mode"] ?? "")?>
							</select>

							<?php echo $lang_torrents['text_mode'] ?>
						</td>
					</tr>
<?php
$Cache->new_page('hot_search', 3670, true);
if (!$Cache->get_page()){
    \App\Repositories\TorrentListingRepository::cleanupSuggest();
    $searchres = \App\Repositories\TorrentListingRepository::getHotSearch();
    $hotcount = 0;
    $hotsearch = "";
    foreach ($searchres as $searchrow)
    {
        $hotsearch .= "<a href=\"".htmlspecialchars("?search=" . rawurlencode($searchrow["keywords"]) . "&notnewword=1")."\"><u>" . htmlspecialchars($searchrow["keywords"]) . "</u></a>&nbsp;&nbsp;";
        $hotcount += mb_strlen($searchrow["keywords"],"UTF-8");
        if ($hotcount > 60)
            break;
    }
    $Cache->add_whole_row();
    if ($hotsearch)
    print("<tr><td class=\"embedded\" colspan=\"3\">&nbsp;&nbsp;".$hotsearch."</td></tr>");
    $Cache->end_whole_row();
    $Cache->cache_page();
}
echo $Cache->next_row();

if ($allTags->isNotEmpty()) {
    echo '<tr><td colspan="3" class="embedded" style="padding-top: 4px">' . $tagRep->renderSpan($sectiontype, ['*'], true) . '</td></tr>';
}

?>

				</table>
			</td>
			<td class="rowfollow" align="center">
				<input type="submit" class="btn" value="<?php echo $lang_torrents['submit_go'] ?>" />
			</td>
		</tr>
		</tbody>
	</table>
	</form>
<?php
}
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
