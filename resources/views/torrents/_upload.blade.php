<?php
$offerRows = (array) ($offerRows ?? []);
?>
	<form id="compose" enctype="multipart/form-data" action="/takeupload" method="post" name="upload">
			<?php
			print("<p align=\"center\">".$lang_upload['text_red_star_required']."</p>");
			?>
			<table border="1" cellspacing="0" cellpadding="5" width="97%">
				<tr>
					<td class='colhead' colspan='2' align='center'>
						<?php echo $lang_upload['text_tracker_url'] ?>: &nbsp;&nbsp;&nbsp;&nbsp;<b><?php echo  \App\Support\Tracker::schemaAndHost((int) $CURUSER['tracker_url_id'], (bool) true)?></b>
						<?php
						if(!is_writable(\App\Support\Path::resolve($torrent_dir, \ROOT_PATH)))
						print("<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!");
						if(!$max_torrent_size)
						print("<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!");
						?>
					</td>
				</tr>
				<?php
				\App\Support\Html::tr($lang_upload['row_torrent_file']."<font color=\"red\">*</font>", "<input type=\"file\" class=\"file\" id=\"torrent\" name=\"file\" onchange=\"getname()\" />\n", 1);
				if ($altname_main == 'yes'){
					\App\Support\Html::tr($lang_upload['row_torrent_name'], "<b>".$lang_upload['text_english_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px;\" name=\"name\" />&nbsp;&nbsp;&nbsp;
<b>".$lang_upload['text_chinese_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px\" name=\"cnname\"><br /><font class=\"medium\">".$lang_upload['text_titles_note']."</font>", 1);
				} else {
				    $autoFillText = $lang_upload['fill_setlist'];
				    $nameInput = $torrentRep->buildUploadFieldInput("name", "", $lang_upload['text_torrent_name_note'], $autoFillText, 'setlistLookupBtn', 'lookupSetlist()');
                    \App\Support\Html::tr($lang_upload['row_torrent_name'], $nameInput, 1);
                }

                //price
                if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_SET_PRICE) && \App\Support\Config\SiteConfig::current()->torrent->paidTorrentEnabled()) {
                    $maxPrice = \App\Support\Config\SiteConfig::current()->torrent->maxPrice();
                    $pricePlaceholder = "";
                    if ($maxPrice > 0) {
                        $pricePlaceholder = \App\Support\Locale::trans("label.torrent.max_price_help", ["max_price" => $maxPrice], null);
                    }
                    \App\Support\Html::tr(\App\Support\Locale::trans('label.torrent.price', [], null), '<input type="number" min="0" name="price" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;' . \App\Support\Locale::trans('label.torrent.price_help', ['tax_factor' => \App\Support\Config\SiteConfig::current()->torrent->taxFactor() * 100 . '%'], null), 1);
                }

				print("<tr><td class=\"rowhead\" style='padding: 3px' valign=\"top\">".$lang_upload['row_description']."<font color=\"red\">*</font></td><td class=\"rowfollow\">");
				echo \App\Support\Form::bbcodeEditor("upload","descr", "", false, 130, true);
				print("</td></tr>\n");

                if (\App\Support\Config\SiteConfig::current()->main->enableTechnicalInfo()) {
                    \App\Support\Html::tr($lang_functions['text_technical_info'], '<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>' . $lang_functions['text_technical_info_help_text'], 1);
                }

				$s = "<select name=\"type\" id=\"browsecat\" data-mode='$browsecatmode'>\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
			foreach ($cats as $row) {
				$s .= "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["name"]) . "</option>\n";
			}
			$s .= "</select>\n";
			\App\Support\Html::tr($lang_upload['row_type']."<font color=\"red\">*</font>", $s, 1);
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
                $customField = new \App\Support\CustomField();
                $hitAndRunRep = new \App\Repositories\HitAndRunRepository();
                echo "<tbody id=\"browsecat_section\" data-mode=\"$browsecatmode\">\n";
				$selectNormal = $searchBoxRep->renderTaxonomySelect($browsecatmode);
				\App\Support\Html::tr($lang_upload['row_quality'], $selectNormal, 1, "mode_$browsecatmode");
				echo $customField->renderOnUploadPage(0, $browsecatmode);
				echo $hitAndRunRep->renderOnUploadPage('', $browsecatmode);
				\App\Support\Html::tr($lang_functions['text_tags'], $tagRep->renderCheckbox($browsecatmode), 1, "mode_$browsecatmode");
				echo "</tbody>\n";


				//==== offer dropdown for offer mod  from code by S4NE
				if (! empty($offerRows))
				{
					$offer = "<select name=\"offer\"><option value=\"0\">".$lang_upload['select_choose_one']."</option>";
					foreach ($offerRows as $offerrow)
						$offer .= "<option value=\"" . (int) $offerrow['id'] . "\">" . htmlspecialchars($offerrow['name']) . "</option>";
					$offer .= "</select>";
					\App\Support\Html::tr($lang_upload['row_your_offer']. (!$uploadFreely ? "<font color=red>*</font>" : ""), $offer.$lang_upload['text_please_select_offer'], 1);
					$getOfferJs = <<<JS
document.querySelector('select[name="offer"]').addEventListener("change", function () {
    var id = this.value
    if (id == 0) {
        return
    }
    var params = {action: "getOffer", params: {id: id}}
    nativePost("ajax.php", params, function (response) {
        console.log(response)
        if (response.ret != 0) {
            alert(response.msg)
            return
        }
        var nameEl = document.getElementById("name")
        if (nameEl) nameEl.value = response.data.name
        clearContent()
        doInsert(response.data.descr, '', false)
        var catEl = document.getElementById("browsecat")
        if (catEl) {
            catEl.disabled = false
            catEl.value = response.data.category
            catEl.dispatchEvent(new Event('change'))
        }
    })
})
JS;
					\App\Support\AssetAppender::js($getOfferJs, 'footer', false);

				}
				//===end

                //pick
                $pickcontent = '';
                if(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_SET_STICKY))
                {
                    $options = [];
                    foreach (\App\Models\Torrent::listPosStates() as $key => $value) {
                        $options[] = "<option value=\"" . $key . "\">".$value['text']."</option>";
                    }
                    $pickcontent .= "<b>".$lang_edit['row_torrent_position'].":&nbsp;</b>"."<select name=\"pos_state\" style=\"width: 100px;\">" . implode('', $options) . "</select>&nbsp;&nbsp;&nbsp;";
                    $pickcontent .= \App\Support\Form::datetimepickerInput('pos_state_until', '', \App\Support\Locale::trans('label.deadline', [], null) . ":&nbsp;", ['require_files' => true]);
                }
                if ($pickcontent) {
                    \App\Support\Html::tr($lang_edit['row_pick'], $pickcontent, 1);
                }

				if(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::BE_ANONYMOUS))
				{
					\App\Support\Html::tr($lang_upload['row_show_uploader'], "<input type=\"checkbox\" name=\"uplver\" value=\"yes\" />".$lang_upload['checkbox_hide_uploader_note'], 1);
				}
				?>
				<tr><td class="toolbox" align="center" colspan="2"><b><?php echo $lang_upload['text_read_rules']?></b> <input id="qr" type="submit" class="btn" value="<?php echo $lang_upload['submit_upload']?>" /></td></tr>
		</table>
	</form>
<?php
$customFieldJs = <<<JS
document.getElementById("compose").addEventListener("change", function (e) {
    if (!e.target || !e.target.matches("select[name=type]")) return;
    var mode = e.target.getAttribute("data-mode");
    var value = e.target.value;
    console.log(mode)
    document.querySelectorAll("tr[relation]").forEach(function (tr) { tr.style.display = 'none'; });
    if (value > 0) {
        document.querySelectorAll('tr[relation="mode_' + mode + '"]').forEach(function (tr) { tr.style.display = ''; });
    }
})
document.querySelectorAll("tr[relation]").forEach(function (tr) { tr.style.display = 'none'; });
JS;
\App\Support\AssetAppender::js($customFieldJs, 'footer', false);
?>