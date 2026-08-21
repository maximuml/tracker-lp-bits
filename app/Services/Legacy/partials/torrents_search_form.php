<?php

use App\Models\Torrent;
use App\Repositories\TorrentListingRepository;
use App\Support\Form;
use App\Support\Html;
use App\Support\SearchBox;
use App\Support\SupportContext;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($Cache)) {
    $Cache = SupportContext::getCache();
}
if (! isset($lang_torrents)) {
    $lang_torrents = (array) (SupportContext::getGlobal('lang_torrents') ?? []);
}
$__server_QUERY_STRING = SupportContext::getServerValue('QUERY_STRING');
$searchBoxRightTdStyle = 'padding: 1px;padding-left: 10px;white-space: nowrap';
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
//?>
<!--				</table>-->
                <?php echo SearchBox::buildCategoryTableWithContext($sectiontype, '1', '?', '?', 0, $__server_QUERY_STRING, ['select_unselect' => true, 'user_notifs' => $CURUSER['notifs']])?>
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
								<option value="1"<?php echo $include_dead == 1 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_active'] ?> </option>
								<option value="2"<?php echo $include_dead == 2 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_dead'] ?></option>
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
<?php echo Html::promotionSelection($special_state, 0)?>
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
								<option value="1"<?php echo $inclbookmarked == 1 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_bookmarked'] ?></option>
								<option value="2"<?php echo $inclbookmarked == 2 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_bookmarked_exclude'] ?></option>
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
                                foreach (Torrent::listApprovalStatus(true) as $key => $value) {
                                    printf('<option value="%s"%s>%s</option>', $key, (isset($approvalStatus)) && (string) $approvalStatus === (string) $key ? ' selected' : '', $value);
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
                            <input type="number" min="1" name="size_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('size_begin') ?? '') ?>"/> ~ <input type="number" min="1" name="size_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('size_end') ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['seeders_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="seeders_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('seeders_begin') ?? '') ?>"/> ~ <input type="number" min="1" name="seeders_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('seeders_end') ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['leechers_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="leechers_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('leechers_begin') ?? '') ?>"/> ~ <input type="number" min="1" name="leechers_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('leechers_end') ?? '') ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <font class="medium"><?php echo $lang_torrents['times_completed_range'] ?></font>
                        </td>
                    </tr>
                    <tr>
                        <td class="bottom" style="<?php echo $searchBoxRightTdStyle ?>">
                            <input type="number" min="1" name="times_completed_begin" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('times_completed_begin') ?? '') ?>"/> ~ <input type="number" min="1" name="times_completed_end" style="width: <?php echo $filterInputWidth?>px" value="<?php echo htmlspecialchars(SupportContext::getQuery('times_completed_end') ?? '') ?>"/>
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
                                Form::datetimepickerInput('added_begin', htmlspecialchars(SupportContext::getQuery('added_begin') ?? ''), '', ['require_files' => true, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
                                Form::datetimepickerInput('added_end', htmlspecialchars(SupportContext::getQuery('added_end') ?? ''), '', ['require_files' => false, 'format' => 'Y-m-d', 'style' => 'width: '.$filterInputWidth.'px']),
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
										<input id="searchinput" name="search" type="text" value="<?php echo $searchstr_ori ?>" autocomplete="off" style="width: 200px" oninput="meiliSuggestInput(this.value)" onkeydown="meiliSuggestKey(event)"/>
										<script src="js/meili_autocomplete.js" type="text/javascript"></script>
									</td>
								</tr>
							</table>
						</td>
						<td class="embedded">
							<?php echo '&nbsp;'.$lang_torrents['text_in'] ?>

							<select name="search_area">
								<option value="0"><?php echo $lang_torrents['select_title'] ?></option>
								<option value="1"<?php echo (SupportContext::getQuery('search_area') !== null) && SupportContext::getQuery('search_area') == 1 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_description'] ?></option>
								<option value="3"<?php echo (SupportContext::getQuery('search_area') !== null) && SupportContext::getQuery('search_area') == 3 ? ' selected="selected"' : ''; ?>><?php echo $lang_torrents['select_uploader'] ?></option>
							</select>

							<?php echo $lang_torrents['text_with'] ?>

							<select name="search_mode" style="width: 60px;">
                                <?php echo App\Models\SearchBox::listSelectModeOptions(SupportContext::getQuery('search_mode') ?? '')?>
							</select>

							<?php echo $lang_torrents['text_mode'] ?>
						</td>
					</tr>
<?php
$Cache->new_page('hot_search', 3670, true);
if (! $Cache->get_page()) {
    TorrentListingRepository::cleanupSuggest();
    $searchres = TorrentListingRepository::getHotSearch();
    $hotcount = 0;
    $hotsearch = '';
    foreach ($searchres as $searchrow) {
        $hotsearch .= '<a href="'.htmlspecialchars('?search='.rawurlencode($searchrow['keywords']).'&notnewword=1').'"><u>'.htmlspecialchars($searchrow['keywords']).'</u></a>&nbsp;&nbsp;';
        $hotcount += mb_strlen($searchrow['keywords'], 'UTF-8');
        if ($hotcount > 60) {
            break;
        }
    }
    $Cache->add_whole_row();
    if ($hotsearch) {
        echo '<tr><td class="embedded" colspan="3">&nbsp;&nbsp;'.$hotsearch.'</td></tr>';
    }
    $Cache->end_whole_row();
    $Cache->cache_page();
}
echo $Cache->next_row();

if ($allTags->isNotEmpty()) {
    echo '<tr><td colspan="3" class="embedded" style="padding-top: 4px">'.$tagRep->renderSpan($sectiontype, ['*'], true).'</td></tr>';
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
?>