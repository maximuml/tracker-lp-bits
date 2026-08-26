@php
$id = $torrentId;
$row = $torrentRow;
$CURUSER = $currentUser;
$user = $user;
$tagIds = (array) ($tagIds ?? []);
$denyLog = $denyLog ?? null;
$lang_details = \app(\App\Support\Globals::class)->get('lang_details') ?? [];
$lang_functions = \app(\App\Support\Globals::class)->get('lang_functions') ?? [];
$torrentnameprefix = \app(\App\Support\Globals::class)->get('torrentnameprefix') ?? '';

$requestFlags = (array) ($requestFlags ?? []);
$torrentTopHtml = (string) ($torrentTopHtml ?? '');
$editUrl = (string) ($editUrl ?? '');
$uprow = (string) ($uprow ?? '');
$bookmarkMarkup = (string) ($bookmarkMarkup ?? '');
$tagHtml = (string) ($tagHtml ?? '');
$taxonomyRendered = (string) ($taxonomyRendered ?? '');
$downloadUrl = (string) ($downloadUrl ?? '');
$customFieldsHtml = (string) ($customFieldsHtml ?? '');
$technicalInfoResult = (string) ($technicalInfoResult ?? '');
$descr = (string) ($descr ?? '');
$bonusOptions = (array) ($bonusOptions ?? []);
$magicInfo = (array) ($magicInfo ?? []);
$thanksInfo = (array) ($thanksInfo ?? []);
$userDisplayMap = (array) ($userDisplayMap ?? []);
$currentUserHtml = (string) ($currentUserHtml ?? '');

if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE) || $CURUSER["id"] == $row["owner"])
    $owned = 1;
else $owned = 0;

if (empty($requestFlags['cmtpage'])) {
    if (! empty($requestFlags['uploaded'])) {
        print("<h1 align=\"center\">".$lang_details['text_successfully_uploaded']."</h1>");
        print("<p>".$lang_details['text_redownload_torrent_note']."</p>");
    } elseif (! empty($requestFlags['edited'])) {
        print("<h1 align=\"center\">".$lang_details['text_successfully_edited']."</h1>");
        if (! empty($requestFlags['returnto'])) {
            print("<p><b>".$lang_details['text_go_back'] . "<a href=\"".htmlspecialchars($requestFlags['returnto'])."\">" . $lang_details['text_whence_you_came']."</a></b></p>");
        }
    } elseif (! empty($requestFlags['existed'])) {
        print("<h1 align=\"center\" style='color: red'>".$lang_details['torrent_existed']."</h1>");
        if (! empty($requestFlags['returnto'])) {
            print("<p><b>".$lang_details['text_go_back'] . "<a href=\"".htmlspecialchars($requestFlags['returnto'])."\">" . $lang_details['text_whence_you_came']."</a></b></p>");
        }
    }

    print("<h1 align=\"center\" id=\"top\">".$torrentTopHtml."</h1>\n");

        //Banned reason
        if ($row['approval_status'] == \App\Models\Torrent::APPROVAL_STATUS_DENY && $denyLog !== null) {
            $dangerIcon = '<svg t="1655242121471" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46590" width="16" height="16"><path d="M963.555556 856.888889a55.978667 55.978667 0 0 1-55.978667 56.007111c-0.284444 0-0.540444-0.085333-0.824889-0.085333l-0.056889 0.085333H110.734222l-0.654222-1.137778A55.409778 55.409778 0 0 1 56.888889 856.462222c0-9.756444 2.730667-18.773333 7.139555-26.737778l-3.726222-6.599111L453.461333 156.302222A59.335111 59.335111 0 0 1 510.236444 113.777778c26.936889 0 49.436444 18.005333 56.803556 42.552889l389.973333 661.447111-3.669333 6.997333c6.4 9.102222 10.211556 20.138667 10.211556 32.113778z m-497.777778-541.326222l16.014222 312.888889h56.888889l16.014222-312.888889h-88.917333z m44.458666 398.222222a56.888889 56.888889 0 1 0-0.028444 113.749333 56.888889 56.888889 0 0 0 0.028444-113.749333z" p-id="46591" fill="#d81e06" data-spm-anchor-id="a313x.7781069.0.i61" class="selected"></path></svg>';
            printf(
                '<div style="display: flex; justify-content: center;margin-bottom: 10px"><div style="display: flex;background-color: black; color: white;font-weight: bold; padding: 10px 100px">%s&nbsp;%s</div></div>',
                $dangerIcon, \App\Support\Locale::trans('torrent.approval.deny_comment_show', ['reason' => $denyLog->comment], null)
            );
        }

		print("<table width=\"97%\" cellspacing=\"0\" cellpadding=\"5\">\n");

		$editlink = "a title=\"".$lang_details['title_edit_torrent']."\" href=\"$editUrl\"";

		// ------------- start upped by block ------------------//
		if ($CURUSER["id"] == $row["owner"])
			$CURUSER["downloadpos"] = "yes";
		if ($CURUSER["downloadpos"] != "no")
		{
			print("<tr><td class=\"rowhead\" width=\"13%\">".$lang_details['row_download']."</td><td class=\"rowfollow\" width=\"87%\" align=\"left\">");
			if ($CURUSER['timetype'] != 'timealive')
				$uploadtime = $lang_details['text_at'].$row['added'];
			else $uploadtime = $lang_details['text_blank'].\App\Support\Time::format($row['added'],true,false);
			print("<a class=\"index\" href=\"download.php?id=$id\">" . htmlspecialchars($torrentnameprefix ."." .$row["save_as"]) . ".torrent</a>&nbsp;&nbsp;<a id=\"bookmark0\" href=\"javascript: bookmark(".$row['id'].",0);\">".$bookmarkMarkup."</a>&nbsp;&nbsp;&nbsp;".$lang_details['row_upped_by']."&nbsp;".$uprow.$uploadtime);
			print("</td></tr>");
		}
		else
			\App\Support\Html::tr($lang_details['row_download'], $lang_details['text_downloading_not_allowed']);
		//tag
        if (! empty($tagHtml)) {
            \App\Support\Html::tr($lang_details['row_tags'], $tagHtml, true);
        }

		$size_info =  "<b>".$lang_details['text_size']."</b>" . \App\Support\Format::size($row["size"]);
		$type_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['row_type'].":</b>&nbsp;".$row["cat_name"];
        \App\Support\Html::tr($lang_details['row_basic_info'], $size_info.$type_info.$taxonomyRendered, 1);
		$actions = [];
        if ($CURUSER["downloadpos"] != "no") {
            $hasBuy = (bool) ($hasBuy ?? false);
            if ($row['price'] > 0) {
                if ($hasBuy) {
                    $downloadBtn = $lang_details['text_download_bought_torrent'];
                } else {
                    $downloadBtn = sprintf($lang_details['text_download_paid_torrent'], number_format($row['price']));
                }
            } else {
                $downloadBtn = $lang_details['text_download_torrent'];
            }
            $actions[] = "<a title=\"".$lang_details['title_download_torrent']."\" href=\"download.php?id=".$id."\"><img class=\"dt_download\" src=\"pic/trans.gif\" alt=\"download\" />&nbsp;<b><font class=\"small\">".$downloadBtn."</font></b></a>";
        }
        if ($owned == 1) {
            $actions[] = "<$editlink><img class=\"dt_edit\" src=\"pic/trans.gif\" alt=\"edit\" />&nbsp;<b><font class=\"small\">".(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE) ? $lang_details['text_edit_and_delete_torrent'] : $lang_details['text_edit_torrent']). "</font></b></a>";
        }
        if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::ASK_RESEED) && $row['seeders'] == 0) {
            $actions[] = "<a title=\"".$lang_details['title_ask_for_reseed']."\" href=\"takereseed.php?reseedid=$id\"><img class=\"dt_reseed\" src=\"pic/trans.gif\" alt=\"reseed\">&nbsp;<b><font class=\"small\">".$lang_details['text_ask_for_reseed'] ."</font></b></a>";
        }
        if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_APPROVAL) && (\App\Support\Config\SiteConfig::current()->torrent->approvalStatusIconEnabled() || !\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible())) {
            $approvalIcon = '<svg t="1655224943277" class="icon" viewBox="0 0 1397 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="45530" width="16" height="16"><path d="M1396.363636 121.018182c0 0-223.418182 74.472727-484.072727 372.363636-242.036364 269.963636-297.890909 381.672727-390.981818 530.618182C512 1014.690909 372.363636 744.727273 0 549.236364l195.490909-186.181818c0 0 176.872727 121.018182 297.890909 344.436364 0 0 307.2-474.763636 902.981818-707.490909L1396.363636 121.018182 1396.363636 121.018182zM1396.363636 121.018182" p-id="45531" fill="#e78d0f"></path></svg>';
            $actions[] = sprintf(
                '<a href="javascript:;"><b><font id="approval" class="small approval" data-torrent_id="%s">%s&nbsp;%s</font></b></a>',
                $row['id'], $approvalIcon, $lang_details['action_approval']
            );
            $title = \App\Support\Locale::trans('torrent.approval.modal_title', [], null);
            $js = <<<JS
jQuery('#approval').on("click", function () {
    let torrentId = jQuery(this).attr('data-torrent_id')
    layer.open({
        type: 2,
        title: '$title',
        area: ['60%', '600px'],
        content: '/web/torrent-approval-page?torrent_id=' + torrentId,
    })
})
JS;
            \Nexus\Nexus::js($js, 'footer', false);
        }
        $actions = \App\Support\Hooks::applyFilter('torrent_detail_actions', $actions, $row);
        $actions[] = "<a title=\"".$lang_details['title_report_torrent']."\" href=\"report.php?torrent=$id\"><img class=\"dt_report\" src=\"pic/trans.gif\" alt=\"report\" />&nbsp;<b><font class=\"small\">".$lang_details['text_report_torrent']."</font></b></a>";
		\App\Support\Html::tr($lang_details['row_action'], implode('&nbsp;|&nbsp;', $actions), 1);

        \App\Support\Html::tr($lang_details['torrent_dl_url'],sprintf('<a title="%s" href="%s">%s</a>',$lang_details['torrent_dl_url_notice'], $downloadUrl, $lang_details['torrent_dl_url_text']),1);



        //hook before desc
        \App\Support\Hooks::doAction('torrent_detail_before_desc', $row['id'], $CURUSER['id']);

        /**************start custom fields****************/
        echo $customFieldsHtml;

        /**************end custom fields****************/

        //technical info
        if (! empty($technicalInfoResult)) {
            \App\Support\Html::tr($lang_functions['text_technical_info'], $technicalInfoResult, 1);
        }

		if ($CURUSER['showdescription'] != 'no' && !empty($descr)){
            $desc = \App\Support\Hooks::applyFilter('torrent_detail_description', $descr, $row['id'], $CURUSER['id']);
            \App\Support\Html::tr("<a href=\"javascript: klappe_news('descr')\"><span class=\"nowrap\"><img class=\"minus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picdescr\" title=\"".($lang_details['title_show_or_hide'] ?? '')."\" /> ".$lang_details['row_description']."</span></a>", "<div id='kdescr'>".$desc."</div>", 1);
		}



		if ($row["type"] == "multi")
		{
			$files_info = "<b>".$lang_details['text_num_files']."</b>". $row["numfiles"] . $lang_details['text_files'] . "<br />";
			$files_info .= "<span id=\"showfl\"><a href=\"javascript: viewfilelist(".$id.")\" >".$lang_details['text_see_full_list']."</a></span><span id=\"hidefl\" style=\"display: none;\"><a href=\"javascript: hidefilelist()\">".$lang_details['text_hide_list']."</a></span>";
		}
		function hex_esc($matches) {
			return sprintf("%02x", ord($matches[0]));
		}
		$infoTds = [];
		if (!empty($files_info)) {
		    $infoTds[] = "<td class=\"no_border_wide\">" . $files_info . "</td>";
        }
		$infoTds[] = "<td class=\"no_border_wide\"><b>".$lang_details['row_info_hash'].":</b>&nbsp;".preg_replace_callback('/./s', "hex_esc", \App\Support\Strings::padHash($row["info_hash"]))."</td>";
		if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_STRUCTURE)) {
		    $infoTds[] = "<td class=\"no_border_wide\"><b>" . $lang_details['text_torrent_structure'] . "</b><a href=\"torrent_info.php?id=".$id."\">".$lang_details['text_torrent_info_note']."</a></td>";
        }
        \App\Support\Html::tr($lang_details['row_torrent_info'], "<table><tr>" . implode("", $infoTds) . "</tr></table><span id='filelist'></span>",1);
		\App\Support\Html::tr($lang_details['row_hot_meter'], "<table><tr><td class=\"no_border_wide\"><b>" . $lang_details['text_views']."</b>". $row["views"] . "</td><td class=\"no_border_wide\"><b>" . $lang_details['text_hits']. "</b>" . $row["hits"] . "</td><td class=\"no_border_wide\"><b>" .$lang_details['text_snatched'] . "</b><a href=\"viewsnatches.php?id=".$id."\"><b>" . $row["times_completed"]. $lang_details['text_view_snatches'] . "</td><td class=\"no_border_wide\"><b>" . $lang_details['row_last_seeder']. "</b>" . \App\Support\Time::format($row["last_action"]) . "</td></tr></table>",1);

		\App\Support\Html::tr("<span id=\"seeders\"></span><span id=\"leechers\"></span>".$lang_details['row_peers']."<br /><span id=\"showpeer\"><a href=\"javascript: viewpeerlist(".$row['id'].");\" class=\"sublink\">".$lang_details['text_see_full_list']."</a></span><span id=\"hidepeer\" style=\"display: none;\"><a href=\"javascript: hidepeerlist();\" class=\"sublink\">".$lang_details['text_hide_list']."</a></span>", "<div id=\"peercount\"><b>".$row['seeders'].$lang_details['text_seeders'].\App\Support\Strings::addS($row['seeders'])."</b> | <b>".$row['leechers'].$lang_details['text_leechers'].\App\Support\Strings::addS($row['leechers'])."</b></div><div id=\"peerlist\"></div>" , 1);
		if ($requestFlags['dllist'] ?? false)
		{
			$scronload = "viewpeerlist(".$row['id'].")";

echo "<script type=\"text/javascript\">\n";
echo $scronload;
echo "</script>";
		}

        //Add 魔力值奖励功能
        $bonus_array = $bonusOptions;
        echo '<style type="text/css">
					ul.magic
					{
						cursor:pointer;
						list-style-type:none;
						padding-left:0px;
					}
					ul.magic li
					{
						margin:0px;text-align:center;float:left;width:40px;margin-right:15px; height:21px;background:url("styles/huise.png") no-repeat;
						padding-left:5px;padding-right:5px;
						line-height:20px;
					}
					ul.magic li:hover
					{
						background:url("styles/boli.png") no-repeat
					}
				</style>
		';
        $magic_value_button = '';

        if ($CURUSER['id'] <> $row['owner']) {
            $arr_temp = $bonus_array;
            $bonus_has = $CURUSER['seedbonus'];
            if(intval($bonus_has) < intval($arr_temp[0])){
                $error_bonus_message = $lang_details['magic_have_no_enough_bonus_value'];
                $button_name = "<input class=\"btn\" type=\"button\" value=\"".$error_bonus_message."\" disabled=\"disabled\" />";
                $magic_value_button .= $button_name;
            }else{
                foreach($arr_temp as $key => $each_temp){
                    $each_temp = intval($each_temp);
                    if ($each_temp > 0 && $each_temp <= $bonus_has) {
                        $button_name = $magic_value_button.$key;
                        $magic_button_id = 'magic_value_'.$key;
                        $each_temp_font = '<font style="font-size:8pt;padding-right:5px;">'.('+'.$each_temp).'</font>';
                        $error_bonus_message = $lang_details['magic_have_no_enough_bonus_value'];
                        $button_name = "<li onclick=\"saveMagicValue(".$id.",$each_temp);\">".$each_temp_font."</li>";

                        $magic_value_button .= $button_name;
                    }
                }
            }
        }

        $span_description = $lang_details['span_description_have_given'];
        $span = '<input class="btn" type="button" id="magic_add" style="display:none" value="'.$span_description.'" disabled="disabled" />&nbsp;';
        $whether_have_give_value = 0;
        $give_value = array();
        $no_give = "";
        $add_value ="";

        $count_user_number = $magicInfo['count_user_number'];
        $sum_value = $magicInfo['sum_value'];
        $whether_have_give_value = $magicInfo['whether_have_give_value'];
        $add_value = $magicInfo['add_value'];
        foreach ($magicInfo['givers'] as $giver) {
            $give_value_userid = $giver->userid;
            $give_value[] = ($userDisplayMap[$give_value_userid] ?? '') . " ";
        }
        if ($magicInfo['givers']->isEmpty()) {
            $no_give = $lang_details['text_no_magic_added'];
        }

        if((isset($bonus_has)) && (isset($arr_temp)) && intval($bonus_has) < intval($arr_temp[0])){

        }else if ($whether_have_give_value == 0 ) {
            $magic_value_button = '<ul id="listNumber" class="magic">'.$magic_value_button.'</ul>';
        } else {
            $add_value = str_replace("Number",$add_value,$lang_details['magic_value_number']);
            $magic_value_button ="<input class=\"btn\" type=\"button\" value=\"".$add_value."\" disabled=\"disabled\" />";
            //$give_value = get_username($CURUSER['id'])." ".$give_value;
        }

        $show_list = null;
        $show_all = null;
        $show_list_new_number = 6;
        $other_user_str = null;
        $other_user_span = null;
        if(count($give_value) > 0){
            $count_user_span = '<span id="count_user_spa">'.$count_user_number.'</span>';
            $magic_newest_record = '<span id="magic_newest_record">'. $lang_details['magic_newest_record'].'</span>';
            $show_list_description ='('. $magic_newest_record.$lang_details['magic_sum_user_give_number'].')';
            $show_list_description = str_replace('Number',$count_user_span,$show_list_description);
            $output = array_slice($give_value, 0, $show_list_new_number);
            foreach($output as $eachOutput){
                $show_list .= $eachOutput.'  ';
            }
            //other user list
            if(count($give_value) > $show_list_new_number){
                $show_list .= '<span id="ellipsis">&nbsp;......&nbsp;</span>';
                $show_all_description = '['.$lang_details['magic_show_all_description'].']';
                $show_all = '<a herf="#" style="cursor:pointer" onclick="displayOtherUserList()">'.$show_all_description.'</a>'.'<br/>';
                $other_user_list = array_slice($give_value, $show_list_new_number, count($give_value));
                foreach($other_user_list as $each){
                    $other_user_str .= $each.'  ';
                }
                $other_user_span = '<span id="other_user_list" style="display:none">'.$other_user_str.'</span>';
            }
        }else{
            $show_list_description = null;
            $haveGotBonus = $no_give;
        }
        $current_user_magic = "<span id='current_user_magic' style='display:none'>".$currentUserHtml."</span>&nbsp;";
        $haveGotBonus = $lang_details['magic_haveGotBonus'].'&nbsp';
        $spanSumAll = '<span id="spanSumAll">'.$sum_value.'</span>';
        $haveGotBonus = str_replace('Number',$spanSumAll,$haveGotBonus);
        $firstLine = '<div style="height:25px">'.$magic_value_button.$span.$haveGotBonus.$show_all.'</div>';
        $otherLine = '<div>'.$current_user_magic.$show_list.$other_user_span.$show_list_description.'</div>';
        \App\Support\Html::tr($lang_details['magic_value_award'],$firstLine.$otherLine,1);
        //End 魔力值奖励功能

		// ------------- start thanked-by block--------------//

		$torrentid = $id;
		$thanksby = "";
		$nothanks = "";
		$thanks_said = 0;

		$thanksCount = $thanksInfo['count'];
		$thanks_all = count($thanksInfo['thanks']);
		foreach ($thanksInfo['thanks'] as $t) {
			$thanks_userid = $t->userid;
			if ((int) $t->userid == $CURUSER['id']) {
				$thanks_said = 1;
			} else {
				$thanksby .= ($userDisplayMap[$thanks_userid] ?? '')." ";
			}
		}
		if ($thanks_all == 0) {
			$nothanks = $lang_details['text_no_thanks_added'];
		}
		$thanks_said = $thanksInfo['has_thanked'] ? 1 : 0;
		if ($thanks_said == 0) {
			$buttonvalue = " value=\"".$lang_details['submit_say_thanks']."\"";
		} else {
			$buttonvalue = " value=\"".$lang_details['submit_you_said_thanks']."\" disabled=\"disabled\"";
			$thanksby = $currentUserHtml." ".$thanksby;
		}
		$thanksbutton = "<input class=\"btn\" type=\"button\" id=\"saythanks\"  onclick=\"saythanks(".$torrentid.");\" ".$buttonvalue." />";
		\App\Support\Html::tr($lang_details['row_thanks_by'],"<span id=\"thanksadded\" style=\"display: none;\"><input class=\"btn\" type=\"button\" value=\"".$lang_details['text_thanks_added']."\" disabled=\"disabled\" /></span><span id=\"curuser\" style=\"display: none;\">".$currentUserHtml." </span><span id=\"thanksbutton\">".$thanksbutton."</span>&nbsp;&nbsp;<span id=\"nothanks\">".$nothanks."</span><span id=\"addcuruser\"></span>".$thanksby.($thanks_all < $thanksCount ? $lang_details['text_and_more'].$thanksCount.$lang_details['text_users_in_total'] : ""),1);
		// ------------- end thanked-by block--------------//

		print("</table>\n");
	}
	else {
		print("<h1 id=\"top\">".$lang_details['text_comments_for']."<a href=\"details.php?id=".$id."\">" . htmlspecialchars($row["name"]) . "</a></h1>\n");
	}
@endphp

@include('torrent._comments')
