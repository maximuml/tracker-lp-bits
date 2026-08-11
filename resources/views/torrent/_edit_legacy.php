<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
if (!$id)
	die();

$row = \Nexus\Database\NexusDB::table('torrents')
    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
    ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
    ->where('torrents.id', $id)
    ->select('torrents.*', 'categories.mode as cat_mode', 'torrent_extras.media_info as technical_info', 'torrent_extras.descr')
    ->first();
if (!$row) die();
$row = (array) $row;

/**
 * custom fields
 * @since v1.6
 */
$customField = new \Nexus\Field\Field();
$hitAndRunRep = new \App\Repositories\HitAndRunRepository();
$tagRep = new \App\Repositories\TagRepository();
$tagIdArr = \App\Models\TorrentTag::query()->where('torrent_id', $id)->get()->pluck('tag_id')->toArray();
$searchBoxRep = new \App\Repositories\SearchBoxRepository();
$sectionmode = $row['cat_mode'];
/*
$showsource = (get_searchbox_value($sectionmode, 'showsource') || ($allowmove && get_searchbox_value($othermode, 'showsource'))); //whether show sources or not
$showmedium = (get_searchbox_value($sectionmode, 'showmedium') || ($allowmove && get_searchbox_value($othermode, 'showmedium'))); //whether show media or not
$showcodec = (get_searchbox_value($sectionmode, 'showcodec') || ($allowmove && get_searchbox_value($othermode, 'showcodec'))); //whether show codecs or not
$showstandard = (get_searchbox_value($sectionmode, 'showstandard') || ($allowmove && get_searchbox_value($othermode, 'showstandard'))); //whether show standards or not
$showprocessing = (get_searchbox_value($sectionmode, 'showprocessing') || ($allowmove && get_searchbox_value($othermode, 'showprocessing'))); //whether show processings or not
$showaudiocodec = (get_searchbox_value($sectionmode, 'showaudiocodec') || ($allowmove && get_searchbox_value($othermode, 'showaudiocodec'))); //whether show audio codecs or not
*/
\App\Support\Html::stdhead($lang_edit['head_edit_torrent'] . "\"". $row["name"] . "\"");

if (!(isset($CURUSER)) || ($CURUSER["id"] != $row["owner"] && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE))) {
	print("<h1 align=\"center\">".$lang_edit['text_cannot_edit_torrent']."</h1>");
	echo sprintf("<p>".$lang_edit['text_cannot_edit_torrent_note']."</p>", $__server_REQUEST_URI ?? '');
}
else {
	print("<form method=\"post\" id=\"compose\" name=\"edittorrent\" action=\"takeedit.php\" enctype=\"multipart/form-data\">");
	print("<input type=\"hidden\" name=\"id\" value=\"$id\" />");
	if (((\App\Support\SupportContext::getQuery("returnto") !== null)))
	print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars(\App\Support\SupportContext::getQuery("returnto")) . "\" />");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n");
	print("<tr><td class='colhead' colspan='2' align='center'>".htmlspecialchars($row["name"])."</td></tr>");
	\App\Support\Html::tr($lang_edit['row_torrent_name']."<font color=\"red\">*</font>", "<input type=\"text\" style=\"width: 99%;\" name=\"name\" value=\"" . htmlspecialchars($row["name"]) . "\" />", 1);

    //price
    if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_SET_PRICE) && \App\Support\Config\SiteConfig::current()->torrent->paidTorrentEnabled()) {
        $maxPrice = \App\Support\Config\SiteConfig::current()->torrent->maxPrice();
        $pricePlaceholder = "";
        if ($maxPrice > 0) {
            $pricePlaceholder = nexus_trans("label.torrent.max_price_help", ["max_price" => $maxPrice]);
        }
        \App\Support\Html::tr(nexus_trans('label.torrent.price'), '<input type="number" min="0" name="price" value="'.$row['price'].'" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;' . nexus_trans('label.torrent.price_help', ['tax_factor' => (\App\Support\Config\SiteConfig::current()->torrent->taxFactor() * 100) . '%']), 1);
    }

    print("<tr><td class=\"rowhead\">".$lang_edit['row_description']."<font color=\"red\">*</font></td><td class=\"rowfollow\">");
	echo \App\Support\Form::bbcodeEditor("edittorrent","descr",($row["descr"]), false, 130, true);
	print("</td></tr>");

    if (\App\Support\Config\SiteConfig::current()->main->enableTechnicalInfo()) {
        \App\Support\Html::tr($lang_functions['text_technical_info'], '<textarea name="technical_info" rows="8" style="width: 99%;">' . $row['technical_info'] . '</textarea><br/>' . $lang_functions['text_technical_info_help_text'], 1);
    }

	$s = "<select name=\"type\" data-mode='$sectionmode'>";

	$cats = genrelist($sectionmode);
	foreach ($cats as $subrow) {
		$s .= "<option value=\"" . $subrow["id"] . "\"";
		if ($subrow["id"] == $row["category"])
		$s .= " selected=\"selected\"";
		$s .= ">" . htmlspecialchars($subrow["name"]) . "</option>\n";
	}

	$s .= "</select>\n";
	\App\Support\Html::tr($lang_edit['row_type']."<font color=\"red\">*</font>", $s, 1);
/*
	if ($showsource || $showmedium || $showcodec || $showaudiocodec || $showstandard || $showprocessing){
		if ($showsource){
			$source_select = torrent_selection($lang_edit['text_source'],"source_sel","sources",$row["source"]);
		}
		else $source_select = "";

		if ($showmedium){
			$medium_select = torrent_selection($lang_edit['text_medium'],"medium_sel","media",$row["medium"]);
		}
		else $medium_select = "";

		if ($showcodec){
			$codec_select = torrent_selection($lang_edit['text_codec'],"codec_sel","codecs",$row["codec"]);
		}
		else $codec_select = "";

		if ($showaudiocodec){
			$audiocodec_select = torrent_selection($lang_edit['text_audio_codec'],"audiocodec_sel","audiocodecs",$row["audiocodec"]);
		}
		else $audiocodec_select = "";

		if ($showstandard){
			$standard_select = torrent_selection($lang_edit['text_standard'],"standard_sel","standards",$row["standard"]);
		}
		else $standard_select = "";

		if ($showprocessing){
			$processing_select = torrent_selection($lang_edit['text_processing'],"processing_sel","processings",$row["processing"]);
		}
		else $processing_select = "";

		tr($lang_edit['row_quality'], $source_select . $medium_select . $codec_select . $audiocodec_select. $standard_select . $processing_select, 1);
	}

*/

    $select = $searchBoxRep->renderTaxonomySelect($sectionmode, $row);
    \App\Support\Html::tr($lang_edit['row_quality'], $select, 1, "mode_$sectionmode");
    echo $customField->renderOnUploadPage($id, $sectionmode);
    echo $hitAndRunRep->renderOnUploadPage($row['hr'], $sectionmode);
    \App\Support\Html::tr($lang_functions['text_tags'], $tagRep->renderCheckbox($sectionmode, $tagIdArr), 1, "mode_$sectionmode");

	$rowChecks = [];
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::BE_ANONYMOUS) || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE)) {
	    $rowChecks[] = "<label><input type=\"checkbox\" name=\"anonymous\"" . ($row["anonymous"] == "yes" ? " checked=\"checked\"" : "" ) . " value=\"1\" />".$lang_edit['checkbox_anonymous_note']."</label>";
    }
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE)) {
	    array_unshift($rowChecks, "<label><input id='visible' type=\"checkbox\" name=\"visible\"" . ($row["visible"] == "yes" ? " checked=\"checked\"" : "" ) . " value=\"1\" />".$lang_edit['checkbox_visible']."</label>");
    }
	if (!empty($rowChecks)) {
        \App\Support\Html::tr($lang_edit['row_check'], implode('&nbsp;&nbsp;', $rowChecks), 1);
    }

	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_SET_STICKY) || (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE) && $CURUSER["picker"] == 'yes')){
		$pickcontent = $pickcontentPrefix =  "";

        if(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_ON_PROMOTION))
        {
            $pickcontent .= "<b>".$lang_edit['row_special_torrent']."&nbsp;</b>"."<select name=\"sel_spstate\" style=\"width: 100px;\">" .\App\Support\Html::promotionSelection($row["sp_state"], 0). "</select>&nbsp;&nbsp;&nbsp;".'<select name="promotion_time_type" onchange="if (this.value == \'2\') {document.getElementById(\'promotion_until_note\').style.display = \'\';} else {document.getElementById(\'promotion_until_note\').style.display = \'none\';}"><option value="0"'.($row['promotion_time_type'] == 0 ? ' selected="selected"' : '').'>'.$lang_edit['select_use_global_setting'].'</option><option value="1"'.($row['promotion_time_type'] == 1 ? ' selected="selected"' : '').'>'.$lang_edit['select_forever'].'</option><option value="2"'.($row['promotion_time_type'] == 2 ? ' selected="selected"' : '').'>'.$lang_edit['select_until'].'</option></select><span id="promotion_until_note"'.($row['promotion_time_type'] == 2 ? '' : ' style="display: none;"').'>';
            $pickcontent .= '<input type="text" id="promotionuntiltime" name="promotionuntil" style="width: 120px;" value="'.($row['promotion_until'] > $row['added'] ? $row['promotion_until'] : '').'" />';
            $pickcontent .= '&nbsp;('.$lang_edit['text_ie_for'].'<select name="promotionaddedtime" onchange="document.getElementById(\'promotionuntiltime\').value=this.value;"><option value="'.($row['promotion_until'] > $row['added'] ? $row['promotion_until'] : '').'">'.$lang_edit['text_keep_current'].'</option>';
            foreach (array(900, 1800, 3600, 5400, 7200, 14400, 21600, 28800, 43200, 64800, 86400, 129600, 259200, 604800, 1296000, 2592000, 7776000, 15552000, 31104000) as $seconds) {
                $pickcontent .= getAddedTimeOption(strtotime($row['added']), $seconds);
            }
            $pickcontent .= '</select>)&nbsp;'.$lang_edit['text_promotion_until_note'].'</span>&nbsp;&nbsp;';
        }
		if(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_SET_STICKY))
		{
            if ($pickcontent) {
                $pickcontent .= "<br />";
            }
            $options = [];
            foreach (\App\Models\Torrent::listPosStates() as $key => $value) {
                $options[] = "<option" . (($row["pos_state"] == $key) ? " selected=\"selected\"" : "" ) . " value=\"" . $key . "\">".$value['text']."</option>";
            }
			$pickcontent .= "<b>".$lang_edit['row_torrent_position']."&nbsp;</b>"."<select name=\"pos_state\" style=\"width: 100px;\">" . implode('', $options) . "</select>&nbsp;&nbsp;&nbsp;";
            $pickcontent .= \App\Support\Form::datetimepickerInput('pos_state_until', $row['pos_state_until'], nexus_trans('label.deadline') . "&nbsp;", ['require_files' => true]);
		}
		\App\Support\Html::tr($lang_edit['row_pick'], $pickcontent, 1);
	}

	print("<tr><td class=\"toolbox\" colspan=\"2\" align=\"center\"><input id=\"qr\" type=\"submit\" value=\"".$lang_edit['submit_edit_it']."\" /> <input type=\"reset\" value=\"".$lang_edit['submit_revert_changes']."\" /></td></tr>\n");
	print("</table>\n");
	print("</form>\n");
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_DELETE) && \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE)) {
        print("<br /><br />");
        print("<form method=\"post\" action=\"delete.php\">\n");
        print("<input type=\"hidden\" name=\"id\" value=\"$id\" />\n");
        if (((\App\Support\SupportContext::getQuery("returnto") !== null)))
            print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars(\App\Support\SupportContext::getQuery("returnto")) . "\" />\n");
        print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
        print("<tr><td class=\"colhead\" align=\"left\" style='padding-bottom: 3px' colspan=\"2\">".$lang_edit['text_delete_torrent']."</td></tr>");
        \App\Support\Html::tr("<input name=\"reasontype\" type=\"radio\" value=\"1\" />&nbsp;".$lang_edit['radio_dead'], $lang_edit['text_dead_note'], 1);
        \App\Support\Html::tr("<input name=\"reasontype\" type=\"radio\" value=\"2\" />&nbsp;".$lang_edit['radio_dupe'], "<input type=\"text\" style=\"width: 200px\" name=\"reason[]\" />", 1);
        \App\Support\Html::tr("<input name=\"reasontype\" type=\"radio\" value=\"3\" />&nbsp;".$lang_edit['radio_nuked'], "<input type=\"text\" style=\"width: 200px\" name=\"reason[]\" />", 1);
        \App\Support\Html::tr("<input name=\"reasontype\" type=\"radio\" value=\"4\" />&nbsp;".$lang_edit['radio_rules'], "<input type=\"text\" style=\"width: 200px\" name=\"reason[]\" />".$lang_edit['text_req'], 1);
        \App\Support\Html::tr("<input name=\"reasontype\" type=\"radio\" value=\"5\" checked=\"checked\" />&nbsp;".$lang_edit['radio_other'], "<input type=\"text\" style=\"width: 200px\" name=\"reason[]\" />".$lang_edit['text_req'], 1);
        print("<tr><td class=\"toolbox\" colspan=\"2\" align=\"center\"><input type=\"submit\" style='height: 25px' value=\"".$lang_edit['submit_delete_it']."\" /></td></tr>\n");
        print("</table>");
        print("</form>\n");
    }
    $json_sticky_series = json_encode(array(4, 6, 12, 24, 36, 48, 72, 168, 360));
    echo <<<EOT
<script>
jQuery(function($){
	var date_format = function (date) {
		var seperator1 = "-";
		var seperator2 = ":";
		var month = date.getMonth() + 1;
		var strDate = date.getDate();
		var strHour = date.getHours();
		var strMinute = date.getMinutes();
		var strSecond = date.getSeconds();
		if (month >= 1 && month <= 9) {
			month = "0" + month;
		}
		if (strDate >= 0 && strDate <= 9) {
			strDate = "0" + strDate;
		}
		if (strHour >= 0 && strHour <= 9) strHour = "0" + strHour;
		if (strMinute >= 0 && strMinute <= 9) strMinute = "0" + strMinute;
		if (strSecond >= 0 && strSecond <= 9) strSecond = "0" + strSecond;
		return date.getFullYear() + seperator1 + month + seperator1 + strDate
				+ " " + strHour + seperator2 + strMinute
				+ seperator2 + strSecond;
	}
	var pos_until_select = $("#pos_until_select");
	var pos_until = $("#pos_until");
	$("#pos_group").change(function(){
		if($(this).val() == 0){
			pos_until.hide();
			pos_until_select.hide();
		}else{
			pos_until.show();
			pos_until_select.show();
		}
	}).change();
	var series = $json_sticky_series;
	series.forEach(function(elem){
		var label = elem >= 72 ? parseInt(parseInt(elem) / 24) + "{$lang_functions['text_day']}" : elem + "{$lang_functions['text_hour']}";
		pos_until_select.append('<option value="' + elem + '">' + label + '</option>');
	});
	pos_until_select.change(function(){
		var value = $(this).val();
		if(value == -1){
			pos_until.val("0000-00-00 00:00:00").attr("readonly", true);
		}else if(value == 0){
			pos_until.attr("readonly", false);
		}else if(value > 0){
			var curr = pos_until.val();
			var d = new Date(Date.now() + 3600000 * value);
			pos_until.attr("readonly", true).val(date_format(d));
		}
	}).change();
});
</script>
EOT;
}
\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
\Nexus\Nexus::js('js/ptgen.js', 'footer', true);
\App\Support\Html::stdfoot();
function getAddedTimeOption($timeStamp, $addSeconds) {
    $timeStamp += $addSeconds;
    $timeString = date("Y-m-d H:i:s", $timeStamp);
    return '<option value="'.$timeString.'">'.\App\Support\Format::prettyTimeWithLocale($addSeconds).'</option>';
}
