<?php
?>
	<form id="compose" enctype="multipart/form-data" action="takeupload.php" method="post" name="upload">
			<?php
			print("<p align=\"center\">".$lang_upload['text_red_star_required']."</p>");
			?>
			<table border="1" cellspacing="0" cellpadding="5" width="97%">
				<tr>
					<td class='colhead' colspan='2' align='center'>
						<?php echo $lang_upload['text_tracker_url'] ?>: &nbsp;&nbsp;&nbsp;&nbsp;<b><?php echo  get_tracker_schema_and_host($CURUSER['tracker_url_id'], true)?></b>
						<?php
						if(!is_writable(getFullDirectory($torrent_dir)))
						print("<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!");
						if(!$max_torrent_size)
						print("<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!");
						?>
					</td>
				</tr>
				<?php
				tr($lang_upload['row_torrent_file']."<font color=\"red\">*</font>", "<input type=\"file\" class=\"file\" id=\"torrent\" name=\"file\" onchange=\"getname()\" />\n", 1);
				if ($altname_main == 'yes'){
					tr($lang_upload['row_torrent_name'], "<b>".$lang_upload['text_english_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px;\" name=\"name\" />&nbsp;&nbsp;&nbsp;
<b>".$lang_upload['text_chinese_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px\" name=\"cnname\"><br /><font class=\"medium\">".$lang_upload['text_titles_note']."</font>", 1);
				} else {
				    $autoFillText = $lang_upload['fill_setlist'];
				    $nameInput = $torrentRep->buildUploadFieldInput("name", "", $lang_upload['text_torrent_name_note'], $autoFillText, 'setlistLookupBtn', 'lookupSetlist()');
                    tr($lang_upload['row_torrent_name'], $nameInput, 1);
                }

                //price
                if (user_can('torrent-set-price') && get_setting("torrent.paid_torrent_enabled") == "yes") {
                    $maxPrice = get_setting("torrent.max_price");
                    $pricePlaceholder = "";
                    if ($maxPrice > 0) {
                        $pricePlaceholder = nexus_trans("label.torrent.max_price_help", ["max_price" => $maxPrice]);
                    }
                    tr(nexus_trans('label.torrent.price'), '<input type="number" min="0" name="price" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;' . nexus_trans('label.torrent.price_help', ['tax_factor' => (floatval(get_setting('torrent.tax_factor', 0)) * 100) . '%']), 1);
                }

				print("<tr><td class=\"rowhead\" style='padding: 3px' valign=\"top\">".$lang_upload['row_description']."<font color=\"red\">*</font></td><td class=\"rowfollow\">");
				textbbcode("upload","descr", "", false, 130, true);
				print("</td></tr>\n");

                if ($settingMain['enable_technical_info'] == 'yes') {
                    tr($lang_functions['text_technical_info'], '<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>' . $lang_functions['text_technical_info_help_text'], 1);
                }

				$sections = [];
				if ($allowtorrents) {
					$sections[] = ['id' => 'browsecat', 'mode' => $browsecatmode, 'target' => 'specialcat', 'label' => $lang_upload['text_to_browse_section']];
				}
				if ($allowspecial) {
					$sections[] = ['id' => 'specialcat', 'mode' => $specialcatmode, 'target' => 'browsecat', 'label' => $lang_upload['text_to_special_section']];
				}

				$s = '';
				foreach ($sections as $section) {
					$disabler = $allowtwosec ? " onchange=\"disableother('{$section['id']}','{$section['target']}')\"" : '';
					$select = "<select name=\"type\" id=\"{$section['id']}\" data-mode='{$section['mode']}' {$disabler}>\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
					$cats = genrelist($section['mode']);
					foreach ($cats as $row) {
						$select .= "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["name"]) . "</option>\n";
					}
					$select .= "</select>\n";
					$s .= ($allowtwosec ? $section['label'] : '') . $select;
				}
				tr($lang_upload['row_type']."<font color=\"red\">*</font>", $s.($allowtwosec ? $lang_upload['text_type_note'] : ''), 1);
/*
				if ($showsource || $showmedium || $showcodec || $showaudiocodec || $showstandard || $showprocessing){
					if ($showsource){
						$source_select = torrent_selection($lang_upload['text_source'],"source_sel","sources");
					}
					else $source_select = "";

					if ($showmedium){
						$medium_select = torrent_selection($lang_upload['text_medium'],"medium_sel","media");
					}
					else $medium_select = "";

					if ($showcodec){
						$codec_select = torrent_selection($lang_upload['text_codec'],"codec_sel","codecs");
					}
					else $codec_select = "";

					if ($showaudiocodec){
						$audiocodec_select = torrent_selection($lang_upload['text_audio_codec'],"audiocodec_sel","audiocodecs");
					}
					else $audiocodec_select = "";

					if ($showstandard){
						$standard_select = torrent_selection($lang_upload['text_standard'],"standard_sel","standards");
					}
					else $standard_select = "";

					if ($showprocessing){
						$processing_select = torrent_selection($lang_upload['text_processing'],"processing_sel","processings");
					}
					else $processing_select = "";

					tr($lang_upload['row_quality'], $source_select . $medium_select. $codec_select . $audiocodec_select. $standard_select . $processing_select, 1 );
				}

*/
                $customField = new \Nexus\Field\Field();
                $hitAndRunRep = new \App\Repositories\HitAndRunRepository();
                foreach ($sections as $section) {
                    echo "<tbody id=\"{$section['id']}_section\" data-mode=\"{$section['mode']}\">\n";
                    $selectNormal = $searchBoxRep->renderTaxonomySelect($section['mode']);
                    tr($lang_upload['row_quality'], $selectNormal, 1, "mode_{$section['mode']}");
                    echo $customField->renderOnUploadPage(0, $section['mode']);
                    echo $hitAndRunRep->renderOnUploadPage('', $section['mode']);
                    tr($lang_functions['text_tags'], $tagRep->renderCheckbox($section['mode']), 1, "mode_{$section['mode']}");
                    echo "</tbody>\n";
                }

				//==== offer dropdown for offer mod  from code by S4NE
				$offerRows = \App\Models\Offer::query()->where('userid', $CURUSER['id'])->where('allowed', 'allowed')->orderBy('name')->get();
				if ($offerRows->count() > 0)
				{
					$offer = "<select name=\"offer\"><option value=\"0\">".$lang_upload['select_choose_one']."</option>";
					foreach ($offerRows as $offerrow)
						$offer .= "<option value=\"" . $offerrow->id . "\">" . htmlspecialchars($offerrow->name) . "</option>";
					$offer .= "</select>";
					tr($lang_upload['row_your_offer']. (!$uploadfreely && !$allowspecial ? "<font color=red>*</font>" : ""), $offer.$lang_upload['text_please_select_offer'] , 1);
					$getOfferJs = <<<JS
jQuery('select[name="offer"]').on("change", function () {
    let id = this.value
    if (id == 0) {
        return
    }
    let params = {action: "getOffer", params: {id: id}}
    jQuery.post("ajax.php", params, function (response) {
        console.log(response)
        if (response.ret != 0) {
            alert(response.msg)
            return
        }
        jQuery("#name").val(response.data.name)
        clearContent()
        doInsert(response.data.descr, '', false)
        jQuery("#specialcat").prop('disabled', false).val(0).trigger('change')
        jQuery("#browsecat").prop('disabled', false).val(response.data.category).trigger('change')
    }, 'json')
})
JS;
					\Nexus\Nexus::js($getOfferJs, 'footer', false);

				}
				//===end

                //pick
                $pickcontent = '';
                if(user_can('torrentsticky'))
                {
                    $options = [];
                    foreach (\App\Models\Torrent::listPosStates() as $key => $value) {
                        $options[] = "<option value=\"" . $key . "\">".$value['text']."</option>";
                    }
                    $pickcontent .= "<b>".$lang_edit['row_torrent_position'].":&nbsp;</b>"."<select name=\"pos_state\" style=\"width: 100px;\">" . implode('', $options) . "</select>&nbsp;&nbsp;&nbsp;";
                    $pickcontent .= datetimepicker_input('pos_state_until', '', nexus_trans('label.deadline') . ":&nbsp;", ['require_files' => true]);
                }
                if ($pickcontent) {
                    tr($lang_edit['row_pick'], $pickcontent, 1);
                }

				if(user_can('beanonymous'))
				{
					tr($lang_upload['row_show_uploader'], "<input type=\"checkbox\" name=\"uplver\" value=\"yes\" />".$lang_upload['checkbox_hide_uploader_note'], 1);
				}
				?>
				<tr><td class="toolbox" align="center" colspan="2"><b><?php echo $lang_upload['text_read_rules']?></b> <input id="qr" type="submit" class="btn" value="<?php echo $lang_upload['submit_upload']?>" /></td></tr>
		</table>
	</form>
<?php
\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
\Nexus\Nexus::js('js/ptgen.js', 'footer', true);
$customFieldJs = <<<JS
jQuery("#compose").on("change", "select[name=type]", function () {
    let _this = jQuery(this);
    let mode = _this.attr("data-mode");
    let value = _this.val();
    console.log(mode)
    jQuery("tr[relation]").hide();
    if (value > 0) {
        jQuery("tr[relation=mode_" + mode +"]").show();
    }
})
jQuery("tr[relation]").hide();
JS;
\Nexus\Nexus::js($customFieldJs, 'footer', false);
?>